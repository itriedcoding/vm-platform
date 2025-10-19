# Advanced Virtual Machine Provisioning Platform

A comprehensive, production-ready platform for creating and managing Virtual Machines on local machines and VPS instances. Built with HTML, CSS, JavaScript, PHP, and Shell scripting.

## 🚀 Features

### Core VM Management
- **Multi-Hypervisor Support**: QEMU/KVM, Libvirt, VMware, VirtualBox
- **VM Lifecycle Management**: Create, start, stop, restart, delete VMs
- **Resource Management**: CPU, Memory, Disk allocation and monitoring
- **Template System**: Pre-configured OS templates (Ubuntu, CentOS, Debian, Windows)
- **Network Management**: Bridge, NAT, and isolated network configurations

### Advanced Features
- **Real-time Monitoring**: CPU, Memory, Disk, and Network usage tracking
- **Snapshot Management**: Create and restore VM snapshots
- **Backup System**: Automated and manual VM backups
- **Console Access**: VNC-based VM console with web interface
- **Bulk Operations**: Manage multiple VMs simultaneously
- **User Management**: Multi-user support with role-based access
- **Activity Logging**: Comprehensive audit trail
- **Resource Quotas**: Per-user resource limits

### Security & Networking
- **Firewall Integration**: UFW-based security rules
- **Network Isolation**: Isolated VM networks
- **SSL/TLS Support**: Secure web interface
- **Access Control**: User authentication and authorization
- **IP Management**: Dynamic IP allocation

### Monitoring & Analytics
- **System Metrics**: Real-time system resource monitoring
- **VM Performance**: Individual VM performance tracking
- **Usage Statistics**: Resource utilization analytics
- **Alert System**: Automated monitoring alerts
- **Dashboard**: Comprehensive management dashboard

## 📋 Requirements

### System Requirements
- **OS**: Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- **RAM**: Minimum 4GB, Recommended 8GB+
- **Storage**: Minimum 50GB free space
- **CPU**: x86_64 architecture with virtualization support

### Software Dependencies
- QEMU/KVM
- Libvirt
- PHP 8.1+
- MySQL 8.0+
- Nginx
- UFW Firewall
- VNC Server

## 🛠️ Installation

### Quick Setup
```bash
# Clone the repository
git clone https://github.com/itriedcoding/vm-platform.git
cd vm-platform

# Make setup script executable
chmod +x scripts/vm-setup.sh

# Run the setup script
sudo ./scripts/vm-setup.sh
```

### Manual Installation

1. **Install Dependencies**
```bash
sudo apt update
sudo apt install -y qemu-kvm qemu-utils libvirt-daemon-system libvirt-clients \
    bridge-utils virt-manager wget curl unzip php php-mysql php-curl \
    php-json php-mbstring php-xml mysql-server nginx ufw
```

2. **Configure Database**
```bash
sudo mysql -e "CREATE DATABASE vm_platform;"
sudo mysql -e "CREATE USER 'vm_user'@'localhost' IDENTIFIED BY 'your_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON vm_platform.* TO 'vm_user'@'localhost';"
```

3. **Setup Web Server**
```bash
sudo cp -r . /var/www/vm-platform
sudo chown -R www-data:www-data /var/www/vm-platform
sudo chmod -R 755 /var/www/vm-platform
```

4. **Configure Nginx**
```bash
sudo cp nginx.conf /etc/nginx/sites-available/vm-platform
sudo ln -s /etc/nginx/sites-available/vm-platform /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

## 🎯 Usage

### Web Interface
1. Access the platform at `http://comingsoon...`
2. Register a new account or login
3. Create VMs using the dashboard
4. Manage VMs through the web interface

### Command Line Interface
```bash
# List all VMs
./scripts/vm-manage.sh list

# Create a new VM
./scripts/vm-manage.sh create my-vm ubuntu-20.04

# Start a VM
./scripts/vm-manage.sh start vm_1234567890

# Stop a VM
./scripts/vm-manage.sh stop vm_1234567890

# Create a snapshot
./scripts/vm-manage.sh snapshot vm_1234567890 my-snapshot

# Monitor system
./scripts/vm-manage.sh monitor
```

### API Endpoints
```bash
# Create VM
curl -X POST http://your-server/api/create-vm.php \
  -d "name=my-vm&template=ubuntu-20.04&cpu_cores=2&memory=4&disk_size=50"

# Start VM
curl -X POST http://your-server/api/vm-action.php \
  -H "Content-Type: application/json" \
  -d '{"action":"start","vm_id":"vm_1234567890"}'

# Get system stats
curl http://your-server/api/system-stats.php
```

## 📁 Project Structure

```
vm-platform/
├── api/                    # API endpoints
│   ├── create-vm.php
│   ├── vm-action.php
│   ├── bulk-vm-action.php
│   ├── dashboard-stats.php
│   ├── vm-monitor.php
│   └── system-stats.php
├── assets/                 # Static assets
│   ├── css/
│   │   ├── main.css
│   │   ├── dashboard.css
│   │   ├── vms.css
│   │   └── auth.css
│   └── js/
│       ├── main.js
│       ├── dashboard.js
│       ├── vms.js
│       └── auth.js
├── config/                 # Configuration files
│   └── database.php
├── includes/               # PHP classes
│   ├── auth.php
│   └── vm_manager.php
├── scripts/                # Shell scripts
│   ├── vm-setup.sh
│   └── vm-manage.sh
├── index.php              # Main dashboard
├── login.php              # Authentication
├── vms.php                # VM management
├── console.php            # VM console
├── logout.php             # Logout handler
└── README.md              # This file
```

## 🔧 Configuration

### Database Configuration
Edit `config/database.php`:
```php
private $host = 'localhost';
private $db_name = 'vm_platform';
private $username = 'vm_user';
private $password = 'your_password';
```

### VM Templates
Templates are stored in `/var/lib/vm-platform/templates/`:
- `ubuntu-20.04-server-cloudimg-amd64.img`
- `ubuntu-22.04-server-cloudimg-amd64.img`
- `centos-8-stream-cloudimg-amd64.qcow2`
- `debian-11-generic-amd64.qcow2`

### Network Configuration
Default bridge network: `vmbr0` (192.168.100.0/24)
- Gateway: 192.168.100.1
- DNS: 8.8.8.8, 8.8.4.4

## 🔒 Security

### Firewall Rules
```bash
# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow VNC ports
sudo ufw allow 5900:5999/tcp

# Allow VM network
sudo ufw allow from 192.168.100.0/24
```

### User Permissions
- Users can only manage their own VMs
- Admin users have full system access
- All actions are logged for audit

## 📊 Monitoring

### System Metrics
- CPU usage
- Memory usage
- Disk usage
- Network traffic
- Running VMs count

### VM Metrics
- Individual VM resource usage
- Performance statistics
- Status monitoring
- Activity logs

## 🚨 Troubleshooting

### Common Issues

1. **VM won't start**
   - Check if QEMU is installed
   - Verify user is in kvm group
   - Check disk space

2. **Network issues**
   - Verify bridge configuration
   - Check iptables rules
   - Ensure UFW is configured

3. **Permission errors**
   - Check file permissions
   - Verify user groups
   - Check SELinux status

### Logs
- System logs: `/var/log/vm-platform/`
- Nginx logs: `/var/log/nginx/`
- MySQL logs: `/var/log/mysql/`

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

- **Documentation**: Check this README and inline comments
- **Issues**: Report bugs via GitHub Issues
- **Discussions**: Use GitHub Discussions for questions
- **Email**: support@vm-platform.com

## 🎉 Acknowledgments

- QEMU/KVM for virtualization
- Libvirt for VM management
- PHP community for excellent libraries
- Ubuntu/CentOS for base templates

## 📈 Roadmap

### Upcoming Features
- [ ] Docker container support
- [ ] Kubernetes integration
- [ ] Advanced networking (VLAN, VPN)
- [ ] VM migration between hosts
- [ ] Advanced monitoring with Grafana
- [ ] Mobile app
- [ ] REST API v2
- [ ] WebSocket real-time updates
- [ ] VM templates marketplace
- [ ] Automated scaling

### Version History
- **v1.0.0** - Initial release with core VM management
- **v1.1.0** - Added monitoring and backup features
- **v1.2.0** - Enhanced security and user management
- **v1.3.0** - Added bulk operations and API improvements

---

**Made with ❤️ by itriedcoding**
