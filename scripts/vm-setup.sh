#!/bin/bash

# VM Platform Setup Script
# This script sets up the environment for the VM Platform

set -e

echo "🚀 Setting up VM Platform..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root for security reasons"
   exit 1
fi

# Check if running on supported OS
if [[ ! -f /etc/os-release ]]; then
    print_error "Cannot determine OS version"
    exit 1
fi

source /etc/os-release
print_status "Detected OS: $NAME $VERSION_ID"

# Update package lists
print_status "Updating package lists..."
sudo apt-get update -qq

# Install required packages
print_status "Installing required packages..."
sudo apt-get install -y \
    qemu-kvm \
    qemu-utils \
    libvirt-daemon-system \
    libvirt-clients \
    bridge-utils \
    virt-manager \
    wget \
    curl \
    unzip \
    php \
    php-mysql \
    php-curl \
    php-json \
    php-mbstring \
    php-xml \
    mysql-server \
    nginx \
    ufw \
    iptables-persistent

# Enable and start services
print_status "Enabling and starting services..."
sudo systemctl enable libvirtd
sudo systemctl start libvirtd
sudo systemctl enable mysql
sudo systemctl start mysql
sudo systemctl enable nginx
sudo systemctl start nginx

# Add user to required groups
print_status "Adding user to required groups..."
sudo usermod -a -G libvirt $USER
sudo usermod -a -G kvm $USER

# Create VM platform directories
print_status "Creating VM platform directories..."
sudo mkdir -p /var/lib/vm-platform/vms
sudo mkdir -p /var/lib/vm-platform/backups
sudo mkdir -p /var/lib/vm-platform/templates
sudo mkdir -p /var/lib/vm-platform/snapshots
sudo mkdir -p /var/log/vm-platform

# Set proper permissions
sudo chown -R $USER:$USER /var/lib/vm-platform
sudo chmod -R 755 /var/lib/vm-platform

# Configure networking
print_status "Configuring networking..."
sudo cp /etc/netplan/50-cloud-init.yaml /etc/netplan/50-cloud-init.yaml.backup 2>/dev/null || true

# Create bridge network configuration
sudo tee /etc/netplan/60-vm-platform.yaml > /dev/null <<EOF
network:
  version: 2
  bridges:
    vmbr0:
      interfaces: []
      dhcp4: false
      addresses: [192.168.100.1/24]
      nameservers:
        addresses: [8.8.8.8, 8.8.4.4]
      routes:
        - to: 0.0.0.0/0
          via: 192.168.100.1
EOF

# Apply network configuration
sudo netplan apply

# Configure iptables for NAT
print_status "Configuring iptables for NAT..."
sudo iptables -t nat -A POSTROUTING -s 192.168.100.0/24 -o eth0 -j MASQUERADE
sudo iptables -A FORWARD -i vmbr0 -o eth0 -j ACCEPT
sudo iptables -A FORWARD -i eth0 -o vmbr0 -m state --state RELATED,ESTABLISHED -j ACCEPT

# Save iptables rules
sudo iptables-save > /etc/iptables/rules.v4

# Configure UFW firewall
print_status "Configuring firewall..."
sudo ufw --force enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 5900:5999/tcp  # VNC ports
sudo ufw allow from 192.168.100.0/24

# Configure MySQL
print_status "Configuring MySQL..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS vm_platform;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'vm_user'@'localhost' IDENTIFIED BY 'vm_password_2024';"
sudo mysql -e "GRANT ALL PRIVILEGES ON vm_platform.* TO 'vm_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Configure PHP
print_status "Configuring PHP..."
sudo tee /etc/php/8.1/fpm/conf.d/99-vm-platform.ini > /dev/null <<EOF
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M
EOF

# Configure Nginx
print_status "Configuring Nginx..."
sudo tee /etc/nginx/sites-available/vm-platform > /dev/null <<EOF
server {
    listen 80;
    server_name _;
    root /var/www/vm-platform;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
}
EOF

# Enable the site
sudo ln -sf /etc/nginx/sites-available/vm-platform /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx

# Download and setup VM templates
print_status "Downloading VM templates..."
cd /var/lib/vm-platform/templates

# Ubuntu 20.04 LTS
if [[ ! -f ubuntu-20.04-server-cloudimg-amd64.img ]]; then
    print_status "Downloading Ubuntu 20.04 LTS..."
    wget -q --show-progress https://cloud-images.ubuntu.com/focal/current/focal-server-cloudimg-amd64.img
fi

# Ubuntu 22.04 LTS
if [[ ! -f ubuntu-22.04-server-cloudimg-amd64.img ]]; then
    print_status "Downloading Ubuntu 22.04 LTS..."
    wget -q --show-progress https://cloud-images.ubuntu.com/jammy/current/jammy-server-cloudimg-amd64.img
fi

# CentOS 8
if [[ ! -f centos-8-stream-cloudimg-amd64.qcow2 ]]; then
    print_status "Downloading CentOS 8 Stream..."
    wget -q --show-progress https://cloud.centos.org/centos/8/x86_64/images/CentOS-8-GenericCloud-8.4.2105-20210603.0.x86_64.qcow2 -O centos-8-stream-cloudimg-amd64.qcow2
fi

# Debian 11
if [[ ! -f debian-11-generic-amd64.qcow2 ]]; then
    print_status "Downloading Debian 11..."
    wget -q --show-progress https://cloud.debian.org/images/cloud/bullseye/latest/debian-11-generic-amd64.qcow2
fi

# Set proper permissions on templates
sudo chown -R $USER:$USER /var/lib/vm-platform/templates
sudo chmod -R 644 /var/lib/vm-platform/templates

# Create systemd service for VM platform
print_status "Creating systemd service..."
sudo tee /etc/systemd/system/vm-platform.service > /dev/null <<EOF
[Unit]
Description=VM Platform Service
After=network.target mysql.service

[Service]
Type=simple
User=$USER
Group=$USER
WorkingDirectory=/var/www/vm-platform
ExecStart=/usr/bin/php -S 0.0.0.0:8080
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

# Enable the service
sudo systemctl daemon-reload
sudo systemctl enable vm-platform

# Create log rotation configuration
print_status "Setting up log rotation..."
sudo tee /etc/logrotate.d/vm-platform > /dev/null <<EOF
/var/log/vm-platform/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 $USER $USER
}
EOF

# Create backup script
print_status "Creating backup script..."
sudo tee /usr/local/bin/vm-platform-backup > /dev/null <<EOF
#!/bin/bash
# VM Platform Backup Script

BACKUP_DIR="/var/lib/vm-platform/backups"
DATE=\$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="vm-platform-backup-\$DATE.tar.gz"

echo "Creating backup: \$BACKUP_FILE"

# Create backup
tar -czf "\$BACKUP_DIR/\$BACKUP_FILE" \
    /var/lib/vm-platform/vms \
    /var/lib/vm-platform/templates \
    /var/lib/vm-platform/snapshots \
    /var/www/vm-platform \
    /etc/nginx/sites-available/vm-platform \
    /etc/php/8.1/fpm/conf.d/99-vm-platform.ini

echo "Backup created: \$BACKUP_DIR/\$BACKUP_FILE"

# Remove backups older than 30 days
find \$BACKUP_DIR -name "vm-platform-backup-*.tar.gz" -mtime +30 -delete

echo "Old backups cleaned up"
EOF

sudo chmod +x /usr/local/bin/vm-platform-backup

# Create monitoring script
print_status "Creating monitoring script..."
sudo tee /usr/local/bin/vm-platform-monitor > /dev/null <<EOF
#!/bin/bash
# VM Platform Monitoring Script

LOG_FILE="/var/log/vm-platform/monitor.log"
DATE=\$(date '+%Y-%m-%d %H:%M:%S')

# Check if VMs are running
RUNNING_VMS=\$(ps aux | grep qemu-system-x86_64 | grep -v grep | wc -l)
echo "[\$DATE] Running VMs: \$RUNNING_VMS" >> \$LOG_FILE

# Check disk usage
DISK_USAGE=\$(df -h /var/lib/vm-platform | awk 'NR==2{print \$5}' | sed 's/%//')
echo "[\$DATE] Disk usage: \$DISK_USAGE%" >> \$LOG_FILE

# Check memory usage
MEMORY_USAGE=\$(free | awk 'NR==2{printf "%.1f", \$3*100/\$2}')
echo "[\$DATE] Memory usage: \$MEMORY_USAGE%" >> \$LOG_FILE

# Check if services are running
if ! systemctl is-active --quiet nginx; then
    echo "[\$DATE] WARNING: Nginx is not running" >> \$LOG_FILE
fi

if ! systemctl is-active --quiet mysql; then
    echo "[\$DATE] WARNING: MySQL is not running" >> \$LOG_FILE
fi

if ! systemctl is-active --quiet libvirtd; then
    echo "[\$DATE] WARNING: Libvirt is not running" >> \$LOG_FILE
fi
EOF

sudo chmod +x /usr/local/bin/vm-platform-monitor

# Add monitoring to crontab
print_status "Setting up monitoring cron job..."
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/vm-platform-monitor") | crontab -

# Create cleanup script
print_status "Creating cleanup script..."
sudo tee /usr/local/bin/vm-platform-cleanup > /dev/null <<EOF
#!/bin/bash
# VM Platform Cleanup Script

echo "Cleaning up VM Platform..."

# Clean up old logs
find /var/log/vm-platform -name "*.log" -mtime +7 -delete

# Clean up temporary files
find /tmp -name "vm-*" -mtime +1 -delete

# Clean up orphaned VM files
find /var/lib/vm-platform/vms -name "*.qcow2" -mtime +30 -exec rm -f {} \;

echo "Cleanup completed"
EOF

sudo chmod +x /usr/local/bin/vm-platform-cleanup

# Add cleanup to crontab
print_status "Setting up cleanup cron job..."
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/vm-platform-cleanup") | crontab -

# Final setup
print_status "Performing final setup..."

# Copy web files to nginx directory
sudo cp -r /var/www/vm-platform/* /var/www/vm-platform/ 2>/dev/null || true
sudo chown -R www-data:www-data /var/www/vm-platform
sudo chmod -R 755 /var/www/vm-platform

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm
sudo systemctl restart libvirtd

print_success "VM Platform setup completed successfully!"
print_status "You can now access the platform at: http://$(hostname -I | awk '{print $1}')"
print_status "Default database credentials:"
print_status "  Database: vm_platform"
print_status "  Username: vm_user"
print_status "  Password: vm_password_2024"
print_warning "Please change the default database password for security!"
print_status "To start the platform, run: sudo systemctl start vm-platform"
print_status "To check status, run: sudo systemctl status vm-platform"

echo ""
print_success "🎉 VM Platform is ready to use!"