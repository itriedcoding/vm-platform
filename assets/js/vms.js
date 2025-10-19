// Virtual Machines page JavaScript functionality

class VMsManager {
    constructor() {
        this.currentView = 'grid';
        this.selectedVMs = new Set();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupFilters();
        this.setupBulkActions();
        this.setupVMMonitoring();
    }

    setupEventListeners() {
        // VM checkbox selection
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('vm-checkbox')) {
                this.handleVMSelection(e.target);
            }
            if (e.target.id === 'selectAllVMs') {
                this.handleSelectAll(e.target.checked);
            }
        });

        // VM menu toggle
        document.addEventListener('click', (e) => {
            if (e.target.closest('.menu-btn')) {
                e.stopPropagation();
                this.toggleVMMenu(e.target.closest('.menu-btn'));
            } else {
                this.closeAllVMMenus();
            }
        });

        // VM action buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action]')) {
                const action = e.target.getAttribute('data-action');
                const vmId = e.target.getAttribute('data-vm-id');
                this.handleVMAction(action, vmId);
            }
        });
    }

    setupFilters() {
        const filterInputs = ['statusFilter', 'templateFilter', 'vmSearch'];
        filterInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('change', () => this.applyFilters());
                input.addEventListener('input', () => this.applyFilters());
            }
        });
    }

    setupBulkActions() {
        const bulkActionButtons = document.querySelectorAll('.bulk-action');
        bulkActionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const action = e.target.getAttribute('data-action');
                this.performBulkAction(action);
            });
        });
    }

    setupVMMonitoring() {
        // Start monitoring for running VMs
        const runningVMs = document.querySelectorAll('.vm-card .status-running');
        runningVMs.forEach(vmCard => {
            const vmId = vmCard.getAttribute('data-vm-id');
            this.startVMMonitoring(vmId);
        });
    }

    // View switching
    switchView(view) {
        this.currentView = view;
        
        // Update view buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-view') === view) {
                btn.classList.add('active');
            }
        });

        // Show/hide appropriate view
        const gridView = document.getElementById('vmGrid');
        const listView = document.getElementById('vmList');
        
        if (view === 'grid') {
            gridView.style.display = 'grid';
            listView.style.display = 'none';
        } else {
            gridView.style.display = 'none';
            listView.style.display = 'block';
        }
    }

    // Filtering
    applyFilters() {
        const statusFilter = document.getElementById('statusFilter')?.value;
        const templateFilter = document.getElementById('templateFilter')?.value;
        const searchTerm = document.getElementById('vmSearch')?.value.toLowerCase();

        const vmCards = document.querySelectorAll('.vm-card');
        const vmRows = document.querySelectorAll('.vm-list tbody tr');
        
        let visibleCount = 0;
        
        // Filter grid view
        vmCards.forEach(card => {
            let show = this.shouldShowVM(card, statusFilter, templateFilter, searchTerm);
            card.style.display = show ? 'block' : 'none';
            if (show) visibleCount++;
        });

        // Filter list view
        vmRows.forEach(row => {
            let show = this.shouldShowVM(row, statusFilter, templateFilter, searchTerm);
            row.style.display = show ? 'table-row' : 'none';
        });

        // Update count
        const countElement = document.querySelector('.vm-count h3');
        if (countElement) {
            countElement.textContent = `${visibleCount} Virtual Machines`;
        }
    }

    shouldShowVM(element, statusFilter, templateFilter, searchTerm) {
        let show = true;

        // Status filter
        if (statusFilter && statusFilter !== 'all') {
            const statusElement = element.querySelector('.vm-status');
            if (statusElement) {
                const cardStatus = statusElement.textContent.toLowerCase();
                if (cardStatus !== statusFilter) {
                    show = false;
                }
            }
        }

        // Template filter
        if (templateFilter && templateFilter !== 'all') {
            const cardTemplate = element.getAttribute('data-template');
            if (cardTemplate !== templateFilter) {
                show = false;
            }
        }

        // Search filter
        if (searchTerm) {
            const nameElement = element.querySelector('.vm-title h3, .vm-name strong');
            const idElement = element.querySelector('.vm-id, .vm-name small');
            const name = nameElement ? nameElement.textContent.toLowerCase() : '';
            const id = idElement ? idElement.textContent.toLowerCase() : '';
            
            if (!name.includes(searchTerm) && !id.includes(searchTerm)) {
                show = false;
            }
        }

        return show;
    }

    clearFilters() {
        document.getElementById('statusFilter').value = 'all';
        document.getElementById('templateFilter').value = 'all';
        document.getElementById('vmSearch').value = '';
        this.applyFilters();
    }

    // VM Selection
    handleVMSelection(checkbox) {
        const vmId = checkbox.getAttribute('data-vm-id');
        
        if (checkbox.checked) {
            this.selectedVMs.add(vmId);
        } else {
            this.selectedVMs.delete(vmId);
        }
        
        this.updateBulkActions();
    }

    handleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.vm-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            const vmId = checkbox.getAttribute('data-vm-id');
            
            if (checked) {
                this.selectedVMs.add(vmId);
            } else {
                this.selectedVMs.delete(vmId);
            }
        });
        
        this.updateBulkActions();
    }

    updateBulkActions() {
        const bulkActionsContainer = document.querySelector('.bulk-actions');
        const selectedCountElement = document.getElementById('selectedCount');
        
        if (this.selectedVMs.size > 0) {
            bulkActionsContainer.style.display = 'flex';
            selectedCountElement.textContent = this.selectedVMs.size;
        } else {
            bulkActionsContainer.style.display = 'none';
        }
    }

    // VM Actions
    async handleVMAction(action, vmId) {
        if (!vmId) {
            this.showNotification('VM ID not found', 'error');
            return;
        }

        // Show loading state
        const vmCard = document.querySelector(`[data-vm-id="${vmId}"]`);
        if (vmCard) {
            vmCard.classList.add('loading');
        }

        try {
            const response = await fetch('api/vm-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    vm_id: vmId
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message || 'Action completed successfully', 'success');
                
                // Handle console action specially
                if (action === 'console' && result.console_url) {
                    window.open(result.console_url, `console_${vmId}`, 'width=800,height=600');
                } else {
                    // Refresh the page for other actions
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                this.showNotification(result.message || 'Action failed', 'error');
            }
        } catch (error) {
            console.error('Error performing VM action:', error);
            this.showNotification('An error occurred while performing the action', 'error');
        } finally {
            if (vmCard) {
                vmCard.classList.remove('loading');
            }
        }
    }

    async performBulkAction(action) {
        if (this.selectedVMs.size === 0) {
            this.showNotification('No VMs selected', 'warning');
            return;
        }

        const actionMap = {
            'start': 'Starting VMs...',
            'stop': 'Stopping VMs...',
            'restart': 'Restarting VMs...',
            'delete': 'Deleting VMs...',
            'snapshot': 'Creating snapshots...',
            'backup': 'Creating backups...'
        };

        this.showNotification(actionMap[action] || 'Processing...', 'warning');

        try {
            const response = await fetch('api/bulk-vm-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    vm_ids: Array.from(this.selectedVMs)
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message || 'Bulk action completed successfully', 'success');
                // Clear selection and refresh
                this.selectedVMs.clear();
                this.updateBulkActions();
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showNotification(result.message || 'Bulk action failed', 'error');
            }
        } catch (error) {
            console.error('Error performing bulk action:', error);
            this.showNotification('An error occurred while performing the bulk action', 'error');
        }
    }

    // VM Menu
    toggleVMMenu(button) {
        const dropdown = button.nextElementSibling;
        const isOpen = dropdown.classList.contains('show');
        
        // Close all other menus
        this.closeAllVMMenus();
        
        if (!isOpen) {
            dropdown.classList.add('show');
        }
    }

    closeAllVMMenus() {
        document.querySelectorAll('.vm-menu-dropdown').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }

    // VM Monitoring
    startVMMonitoring(vmId) {
        const monitoringInterval = setInterval(async () => {
            try {
                const response = await fetch(`api/vm-monitor.php?vm_id=${vmId}`);
                const data = await response.json();

                if (data.success) {
                    this.updateVMPerformance(vmId, data.monitoring);
                }
            } catch (error) {
                console.error('Failed to update VM monitoring:', error);
                clearInterval(monitoringInterval);
            }
        }, 5000);

        // Store interval ID for cleanup
        window.vmMonitoringIntervals = window.vmMonitoringIntervals || {};
        window.vmMonitoringIntervals[vmId] = monitoringInterval;
    }

    stopVMMonitoring(vmId) {
        if (window.vmMonitoringIntervals && window.vmMonitoringIntervals[vmId]) {
            clearInterval(window.vmMonitoringIntervals[vmId]);
            delete window.vmMonitoringIntervals[vmId];
        }
    }

    updateVMPerformance(vmId, monitoringData) {
        const vmCard = document.querySelector(`[data-vm-id="${vmId}"]`);
        if (!vmCard) return;

        const performanceContainer = vmCard.querySelector('.vm-performance');
        if (performanceContainer) {
            // Update CPU
            const cpuBar = performanceContainer.querySelector('.performance-item:nth-child(1) .bar-fill');
            const cpuValue = performanceContainer.querySelector('.performance-item:nth-child(1) .value');
            if (cpuBar) cpuBar.style.width = monitoringData.cpu + '%';
            if (cpuValue) cpuValue.textContent = monitoringData.cpu + '%';

            // Update Memory
            const memoryBar = performanceContainer.querySelector('.performance-item:nth-child(2) .bar-fill');
            const memoryValue = performanceContainer.querySelector('.performance-item:nth-child(2) .value');
            if (memoryBar) memoryBar.style.width = monitoringData.memory + '%';
            if (memoryValue) memoryValue.textContent = monitoringData.memory + '%';

            // Update Disk
            const diskBar = performanceContainer.querySelector('.performance-item:nth-child(3) .bar-fill');
            const diskValue = performanceContainer.querySelector('.performance-item:nth-child(3) .value');
            if (diskBar) diskBar.style.width = monitoringData.disk + '%';
            if (diskValue) diskValue.textContent = monitoringData.disk + '%';
        }
    }

    // Utility functions
    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Cleanup on page unload
    destroy() {
        // Clear all VM monitoring intervals
        if (window.vmMonitoringIntervals) {
            Object.values(window.vmMonitoringIntervals).forEach(interval => {
                clearInterval(interval);
            });
            window.vmMonitoringIntervals = {};
        }
    }
}

// Global functions for backward compatibility
function switchView(view) {
    if (window.vmsManager) {
        window.vmsManager.switchView(view);
    }
}

function toggleVMMenu(button) {
    if (window.vmsManager) {
        window.vmsManager.toggleVMMenu(button);
    }
}

function clearFilters() {
    if (window.vmsManager) {
        window.vmsManager.clearFilters();
    }
}

function refreshVMs() {
    window.location.reload();
}

function editVM(vmId) {
    // TODO: Implement VM editing
    alert('VM editing feature coming soon!');
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.vmsManager = new VMsManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.vmsManager) {
        window.vmsManager.destroy();
    }
});