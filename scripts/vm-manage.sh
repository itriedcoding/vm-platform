#!/bin/bash

# VM Management Script
# This script provides command-line management for VMs

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
VM_DIR="/var/lib/vm-platform/vms"
TEMPLATES_DIR="/var/lib/vm-platform/templates"
BACKUP_DIR="/var/lib/vm-platform/backups"

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

# Function to show usage
show_usage() {
    echo "VM Platform Management Script"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  list                    List all VMs"
    echo "  create <name> <template> Create a new VM"
    echo "  start <vm_id>           Start a VM"
    echo "  stop <vm_id>            Stop a VM"
    echo "  restart <vm_id>         Restart a VM"
    echo "  delete <vm_id>          Delete a VM"
    echo "  status <vm_id>          Show VM status"
    echo "  console <vm_id>         Open VM console"
    echo "  snapshot <vm_id> <name> Create VM snapshot"
    echo "  backup <vm_id> <name>   Create VM backup"
    echo "  restore <backup_file>   Restore from backup"
    echo "  templates               List available templates"
    echo "  cleanup                 Clean up old files"
    echo "  monitor                 Show system monitoring"
    echo "  help                    Show this help"
    echo ""
    echo "Examples:"
    echo "  $0 list"
    echo "  $0 create my-vm ubuntu-20.04"
    echo "  $0 start vm_1234567890"
    echo "  $0 snapshot vm_1234567890 my-snapshot"
}

# Function to list VMs
list_vms() {
    print_status "Listing all VMs..."
    echo ""
    printf "%-20s %-15s %-10s %-8s %-8s %-12s %-20s\n" "VM ID" "Name" "Status" "CPU" "Memory" "Disk" "Created"
    echo "--------------------------------------------------------------------------------"
    
    for vm_dir in $VM_DIR/vm_*; do
        if [[ -d "$vm_dir" ]]; then
            vm_id=$(basename "$vm_dir")
            vm_name=$(grep "name = " "$vm_dir/vm.conf" 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "Unknown")
            status=$(ps aux | grep -q "qemu-system-x86_64.*$vm_id" && echo "Running" || echo "Stopped")
            cpu=$(grep "cpus = " "$vm_dir/vm.conf" 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "N/A")
            memory=$(grep "memory = " "$vm_dir/vm.conf" 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "N/A")
            disk_size=$(du -sh "$vm_dir/disk.qcow2" 2>/dev/null | cut -f1 || echo "N/A")
            created=$(stat -c %y "$vm_dir" 2>/dev/null | cut -d' ' -f1 || echo "N/A")
            
            printf "%-20s %-15s %-10s %-8s %-8s %-12s %-20s\n" "$vm_id" "$vm_name" "$status" "$cpu" "${memory}MB" "$disk_size" "$created"
        fi
    done
}

# Function to create VM
create_vm() {
    local name="$1"
    local template="$2"
    
    if [[ -z "$name" || -z "$template" ]]; then
        print_error "Usage: $0 create <name> <template>"
        exit 1
    fi
    
    local vm_id="vm_$(date +%s)"
    local vm_dir="$VM_DIR/$vm_id"
    local template_file="$TEMPLATES_DIR/$template.img"
    
    if [[ ! -f "$template_file" ]]; then
        print_error "Template $template not found. Available templates:"
        list_templates
        exit 1
    fi
    
    print_status "Creating VM: $name (ID: $vm_id)"
    
    # Create VM directory
    mkdir -p "$vm_dir"
    
    # Copy template as disk image
    cp "$template_file" "$vm_dir/disk.qcow2"
    
    # Create VM configuration
    cat > "$vm_dir/vm.conf" <<EOF
[vm]
name = $name
memory = 2048
cpus = 2
disk = $vm_dir/disk.qcow2
network = bridge:vmbr0
vnc = 5900
EOF
    
    print_success "VM created successfully: $vm_id"
    print_status "To start the VM, run: $0 start $vm_id"
}

# Function to start VM
start_vm() {
    local vm_id="$1"
    
    if [[ -z "$vm_id" ]]; then
        print_error "Usage: $0 start <vm_id>"
        exit 1
    fi
    
    local vm_dir="$VM_DIR/$vm_id"
    
    if [[ ! -d "$vm_dir" ]]; then
        print_error "VM $vm_id not found"
        exit 1
    fi
    
    # Check if already running
    if ps aux | grep -q "qemu-system-x86_64.*$vm_id"; then
        print_warning "VM $vm_id is already running"
        return
    fi
    
    print_status "Starting VM: $vm_id"
    
    # Read VM configuration
    local memory=$(grep "memory = " "$vm_dir/vm.conf" | cut -d'=' -f2 | tr -d ' ')
    local cpus=$(grep "cpus = " "$vm_dir/vm.conf" | cut -d'=' -f2 | tr -d ' ')
    local vnc_port=$(grep "vnc = " "$vm_dir/vm.conf" | cut -d'=' -f2 | tr -d ' ')
    
    # Start QEMU
    qemu-system-x86_64 \
        -daemonize \
        -name "$vm_id" \
        -m "$memory" \
        -smp "$cpus" \
        -drive file="$vm_dir/disk.qcow2",format=qcow2 \
        -netdev bridge,id=net0,br=vmbr0 \
        -device virtio-net-pci,netdev=net0 \
        -vnc ":$vnc_port" \
        -monitor unix:"$vm_dir/monitor.sock",server,nowait \
        -pidfile "$vm_dir/vm.pid"
    
    print_success "VM $vm_id started successfully"
    print_status "VNC port: $vnc_port"
    print_status "Monitor socket: $vm_dir/monitor.sock"
}

# Function to stop VM
stop_vm() {
    local vm_id="$1"
    
    if [[ -z "$vm_id" ]]; then
        print_error "Usage: $0 stop <vm_id>"
        exit 1
    fi
    
    local vm_dir="$VM_DIR/$vm_id"
    
    if [[ ! -d "$vm_dir" ]]; then
        print_error "VM $vm_id not found"
        exit 1
    fi
    
    print_status "Stopping VM: $vm_id"
    
    # Try graceful shutdown first
    if [[ -f "$vm_dir/monitor.sock" ]]; then
        echo "system_powerdown" | socat - UNIX-CONNECT:"$vm_dir/monitor.sock" 2>/dev/null || true
        sleep 5
    fi
    
    # Force kill if still running
    if ps aux | grep -q "qemu-system-x86_64.*$vm_id"; then
        pkill -f "qemu-system-x86_64.*$vm_id"
        sleep 2
    fi
    
    # Clean up PID file
    rm -f "$vm_dir/vm.pid"
    rm -f "$vm_dir/monitor.sock"
    
    print_success "VM $vm_id stopped successfully"
}

# Function to restart VM
restart_vm() {
    local vm_id="$1"
    
    if [[ -z "$vm_id" ]]; then
        print_error "Usage: $0 restart <vm_id>"
        exit 1
    fi
    
    print_status "Restarting VM: $vm_id"
    stop_vm "$vm_id"
    sleep 2
    start_vm "$vm_id"
}

# Function to delete VM
delete_vm() {
    local vm_id="$1"
    
    if [[ -z "$vm_id" ]]; then
        print_error "Usage: $0 delete <vm_id>"
        exit 1
    fi
    
    local vm_dir="$VM_DIR/$vm_id"
    
    if [[ ! -d "$vm_dir" ]]; then
        print_error "VM $vm_id not found"
        exit 1
    fi
    
    print_warning "This will permanently delete VM $vm_id and all its data!"
    read -p "Are you sure? (y/N): " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_status "Deleting VM: $vm_id"
        
        # Stop VM first
        stop_vm "$vm_id"
        
        # Remove VM directory
        rm -rf "$vm_dir"
        
        print_success "VM $vm_id deleted successfully"
    else
        print_status "Deletion cancelled"
    fi
}

# Function to show VM status
show_status() {
    local vm_id="$1"
    
    if [[ -z "$vm_id" ]]; then
        print_error "Usage: $0 status <vm_id>"
        exit 1
    fi
    
    local vm_dir="$VM_DIR/$vm_id"
    
    if [[ ! -d "$vm_dir" ]]; then
        print_error "VM $vm_id not found"
        exit 1
    fi
    
    print_status "VM Status: $vm_id"
    echo ""
    
    # Basic info
    local name=$(grep "name = " "$vm_dir/vm.conf" 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "Unknown")
    local memory=$(grep "memory = " "$vm_dir/vm.conf" 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "N/A")
    local cpus=$(grep "cpus = " "$vm_dir/vm.conf" 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "N/A")
    local vnc_port=$(grep "vnc = " "$vm_dir/vm.conf" 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "N/A")
    
    echo "Name: $name"
    echo "Memory: ${memory}MB"
    echo "CPUs: $cpus"
    echo "VNC Port: $vnc_port"
    
    # Running status
    if ps aux | grep -q "qemu-system-x86_64.*$vm_id"; then
        echo "Status: Running"
        echo "PID: $(pgrep -f "qemu-system-x86_64.*$vm_id")"
        
        # Resource usage
        local pid=$(pgrep -f "qemu-system-x86_64.*$vm_id")
        if [[ -n "$pid" ]]; then
            local cpu_usage=$(ps -p "$pid" -o %cpu= | tr -d ' ')
            local mem_usage=$(ps -p "$pid" -o %mem= | tr -d ' ')
            echo "CPU Usage: ${cpu_usage}%"
            echo "Memory Usage: ${mem_usage}%"
        fi
    else
        echo "Status: Stopped"
    fi
    
    # Disk usage
    local disk_size=$(du -sh "$vm_dir/disk.qcow2" 2>/dev/null | cut -f1 || echo "N/A")
    echo "Disk Size: $disk_size"
}

# Function to open VM console
open_console() {
    local vm_id="$1"
    
    if [[ -z "$vm_id" ]]; then
        print_error "Usage: $0 console <vm_id>"
        exit 1
    fi
    
    local vm_dir="$VM_DIR/$vm_id"
    
    if [[ ! -d "$vm_dir" ]]; then
        print_error "VM $vm_id not found"
        exit 1
    fi
    
    local vnc_port=$(grep "vnc = " "$vm_dir/vm.conf" 2>/dev/null | cut -d'=' -f2 | tr -d ' ')
    
    if [[ -z "$vnc_port" ]]; then
        print_error "VNC port not configured for VM $vm_id"
        exit 1
    fi
    
    print_status "Opening console for VM $vm_id"
    print_status "VNC port: $vnc_port"
    
    # Try to open VNC viewer
    if command -v vncviewer &> /dev/null; then
        vncviewer "localhost:$vnc_port" &
    else
        print_warning "VNC viewer not found. You can connect manually to localhost:$vnc_port"
    fi
}

# Function to create snapshot
create_snapshot() {
    local vm_id="$1"
    local snapshot_name="$2"
    
    if [[ -z "$vm_id" || -z "$snapshot_name" ]]; then
        print_error "Usage: $0 snapshot <vm_id> <name>"
        exit 1
    fi
    
    local vm_dir="$VM_DIR/$vm_id"
    
    if [[ ! -d "$vm_dir" ]]; then
        print_error "VM $vm_id not found"
        exit 1
    fi
    
    print_status "Creating snapshot '$snapshot_name' for VM $vm_id"
    
    # Create snapshot
    qemu-img snapshot -c "$snapshot_name" "$vm_dir/disk.qcow2"
    
    print_success "Snapshot '$snapshot_name' created successfully"
}

# Function to create backup
create_backup() {
    local vm_id="$1"
    local backup_name="$2"
    
    if [[ -z "$vm_id" || -z "$backup_name" ]]; then
        print_error "Usage: $0 backup <vm_id> <name>"
        exit 1
    fi
    
    local vm_dir="$VM_DIR/$vm_id"
    local backup_file="$BACKUP_DIR/${vm_id}_${backup_name}_$(date +%Y%m%d_%H%M%S).qcow2"
    
    if [[ ! -d "$vm_dir" ]]; then
        print_error "VM $vm_id not found"
        exit 1
    fi
    
    print_status "Creating backup '$backup_name' for VM $vm_id"
    
    # Stop VM first
    stop_vm "$vm_id"
    
    # Create backup
    cp "$vm_dir/disk.qcow2" "$backup_file"
    
    # Start VM again
    start_vm "$vm_id"
    
    print_success "Backup created: $backup_file"
}

# Function to list templates
list_templates() {
    print_status "Available templates:"
    echo ""
    
    for template in "$TEMPLATES_DIR"/*.img; do
        if [[ -f "$template" ]]; then
            local name=$(basename "$template" .img)
            local size=$(du -sh "$template" | cut -f1)
            echo "  $name ($size)"
        fi
    done
}

# Function to cleanup
cleanup() {
    print_status "Cleaning up old files..."
    
    # Clean up old logs
    find /var/log/vm-platform -name "*.log" -mtime +7 -delete 2>/dev/null || true
    
    # Clean up temporary files
    find /tmp -name "vm-*" -mtime +1 -delete 2>/dev/null || true
    
    # Clean up old backups
    find "$BACKUP_DIR" -name "*.qcow2" -mtime +30 -delete 2>/dev/null || true
    
    print_success "Cleanup completed"
}

# Function to show monitoring
show_monitor() {
    print_status "System Monitoring"
    echo ""
    
    # System resources
    echo "=== System Resources ==="
    echo "CPU Usage: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)%"
    echo "Memory Usage: $(free | awk 'NR==2{printf "%.1f%%", $3*100/$2}')"
    echo "Disk Usage: $(df -h /var/lib/vm-platform | awk 'NR==2{print $5}')"
    echo ""
    
    # Running VMs
    echo "=== Running VMs ==="
    local running_count=$(ps aux | grep qemu-system-x86_64 | grep -v grep | wc -l)
    echo "Running VMs: $running_count"
    
    if [[ $running_count -gt 0 ]]; then
        echo ""
        printf "%-20s %-10s %-10s %-10s\n" "VM ID" "CPU%" "Memory%" "Status"
        echo "------------------------------------------------"
        
        for vm_dir in $VM_DIR/vm_*; do
            if [[ -d "$vm_dir" ]]; then
                local vm_id=$(basename "$vm_dir")
                if ps aux | grep -q "qemu-system-x86_64.*$vm_id"; then
                    local pid=$(pgrep -f "qemu-system-x86_64.*$vm_id")
                    local cpu_usage=$(ps -p "$pid" -o %cpu= 2>/dev/null | tr -d ' ' || echo "0")
                    local mem_usage=$(ps -p "$pid" -o %mem= 2>/dev/null | tr -d ' ' || echo "0")
                    printf "%-20s %-10s %-10s %-10s\n" "$vm_id" "${cpu_usage}%" "${mem_usage}%" "Running"
                fi
            fi
        done
    fi
    echo ""
    
    # Network status
    echo "=== Network Status ==="
    if ip link show vmbr0 &>/dev/null; then
        echo "Bridge vmbr0: Active"
    else
        echo "Bridge vmbr0: Not found"
    fi
    echo ""
}

# Main script logic
case "${1:-help}" in
    list)
        list_vms
        ;;
    create)
        create_vm "$2" "$3"
        ;;
    start)
        start_vm "$2"
        ;;
    stop)
        stop_vm "$2"
        ;;
    restart)
        restart_vm "$2"
        ;;
    delete)
        delete_vm "$2"
        ;;
    status)
        show_status "$2"
        ;;
    console)
        open_console "$2"
        ;;
    snapshot)
        create_snapshot "$2" "$3"
        ;;
    backup)
        create_backup "$2" "$3"
        ;;
    templates)
        list_templates
        ;;
    cleanup)
        cleanup
        ;;
    monitor)
        show_monitor
        ;;
    help|--help|-h)
        show_usage
        ;;
    *)
        print_error "Unknown command: $1"
        echo ""
        show_usage
        exit 1
        ;;
esac