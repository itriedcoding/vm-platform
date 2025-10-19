// Dashboard specific JavaScript functionality

class DashboardManager {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
        this.init();
    }

    init() {
        this.initializeCharts();
        this.setupRealTimeUpdates();
        this.setupVMFilters();
        this.setupSearch();
        this.setupQuickActions();
    }

    initializeCharts() {
        // Initialize resource usage charts
        this.createResourceChart('cpu-usage', 'CPU Usage', 75);
        this.createResourceChart('memory-usage', 'Memory Usage', 60);
        this.createResourceChart('disk-usage', 'Disk Usage', 45);
        this.createResourceChart('network-usage', 'Network Usage', 30);
    }

    createResourceChart(containerId, title, percentage) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const chart = document.createElement('div');
        chart.className = 'resource-chart';
        chart.innerHTML = `
            <div class="chart-header">
                <h4>${title}</h4>
                <span class="chart-percentage">${percentage}%</span>
            </div>
            <div class="chart-bar">
                <div class="chart-fill" style="width: ${percentage}%"></div>
            </div>
        `;

        container.appendChild(chart);
    }

    setupRealTimeUpdates() {
        // Update resource usage every 5 seconds
        this.refreshInterval = setInterval(() => {
            this.updateResourceUsage();
            this.updateVMStatus();
        }, 5000);
    }

    async updateResourceUsage() {
        try {
            const response = await fetch('api/system-stats.php');
            const data = await response.json();

            if (data.success) {
                this.updateResourceCharts(data.stats);
            }
        } catch (error) {
            console.error('Failed to update resource usage:', error);
        }
    }

    updateResourceCharts(stats) {
        // Update CPU usage
        const cpuChart = document.querySelector('#cpu-usage .chart-fill');
        if (cpuChart) {
            cpuChart.style.width = stats.cpu_usage + '%';
            document.querySelector('#cpu-usage .chart-percentage').textContent = stats.cpu_usage + '%';
        }

        // Update Memory usage
        const memoryChart = document.querySelector('#memory-usage .chart-fill');
        if (memoryChart) {
            memoryChart.style.width = stats.memory_usage + '%';
            document.querySelector('#memory-usage .chart-percentage').textContent = stats.memory_usage + '%';
        }

        // Update Disk usage
        const diskChart = document.querySelector('#disk-usage .chart-fill');
        if (diskChart) {
            diskChart.style.width = stats.disk_usage + '%';
            document.querySelector('#disk-usage .chart-percentage').textContent = stats.disk_usage + '%';
        }

        // Update Network usage
        const networkChart = document.querySelector('#network-usage .chart-fill');
        if (networkChart) {
            networkChart.style.width = stats.network_usage + '%';
            document.querySelector('#network-usage .chart-percentage').textContent = stats.network_usage + '%';
        }
    }

    async updateVMStatus() {
        try {
            const response = await fetch('api/vm-status.php');
            const data = await response.json();

            if (data.success) {
                this.updateVMStatusIndicators(data.vms);
            }
        } catch (error) {
            console.error('Failed to update VM status:', error);
        }
    }

    updateVMStatusIndicators(vms) {
        vms.forEach(vm => {
            const vmCard = document.querySelector(`[data-vm-id="${vm.id}"]`);
            if (vmCard) {
                const statusElement = vmCard.querySelector('.vm-status');
                if (statusElement) {
                    statusElement.textContent = vm.status.charAt(0).toUpperCase() + vm.status.slice(1);
                    statusElement.className = `vm-status status-${vm.status}`;
                }

                // Update performance indicators
                this.updateVMPerformance(vmCard, vm);
            }
        });
    }

    updateVMPerformance(vmCard, vm) {
        const performanceContainer = vmCard.querySelector('.vm-performance');
        if (performanceContainer && vm.performance) {
            // Update CPU usage
            const cpuBar = performanceContainer.querySelector('.performance-item:nth-child(1) .bar-fill');
            if (cpuBar) {
                cpuBar.style.width = vm.performance.cpu + '%';
            }

            // Update Memory usage
            const memoryBar = performanceContainer.querySelector('.performance-item:nth-child(2) .bar-fill');
            if (memoryBar) {
                memoryBar.style.width = vm.performance.memory + '%';
            }

            // Update Disk usage
            const diskBar = performanceContainer.querySelector('.performance-item:nth-child(3) .bar-fill');
            if (diskBar) {
                diskBar.style.width = vm.performance.disk + '%';
            }
        }
    }

    setupVMFilters() {
        const filterForm = document.getElementById('vmFilters');
        if (!filterForm) return;

        filterForm.addEventListener('change', () => {
            this.applyFilters();
        });
    }

    applyFilters() {
        const statusFilter = document.getElementById('statusFilter')?.value;
        const templateFilter = document.getElementById('templateFilter')?.value;
        const searchTerm = document.getElementById('vmSearch')?.value.toLowerCase();

        const vmCards = document.querySelectorAll('.vm-card');
        
        vmCards.forEach(card => {
            let show = true;

            // Status filter
            if (statusFilter && statusFilter !== 'all') {
                const cardStatus = card.querySelector('.vm-status').textContent.toLowerCase();
                if (cardStatus !== statusFilter) {
                    show = false;
                }
            }

            // Template filter
            if (templateFilter && templateFilter !== 'all') {
                const cardTemplate = card.getAttribute('data-template');
                if (cardTemplate !== templateFilter) {
                    show = false;
                }
            }

            // Search filter
            if (searchTerm) {
                const cardName = card.querySelector('.vm-header h3').textContent.toLowerCase();
                const cardDescription = card.querySelector('.vm-description')?.textContent.toLowerCase() || '';
                if (!cardName.includes(searchTerm) && !cardDescription.includes(searchTerm)) {
                    show = false;
                }
            }

            card.style.display = show ? 'block' : 'none';
        });
    }

    setupSearch() {
        const searchInput = document.getElementById('vmSearch');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                this.applyFilters();
            });
        }
    }

    setupQuickActions() {
        // Bulk actions
        const selectAllCheckbox = document.getElementById('selectAllVMs');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const vmCheckboxes = document.querySelectorAll('.vm-checkbox');
                vmCheckboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
                this.updateBulkActions();
            });
        }

        // Individual VM selection
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('vm-checkbox')) {
                this.updateBulkActions();
            }
        });

        // Bulk action buttons
        const bulkActions = document.querySelectorAll('.bulk-action');
        bulkActions.forEach(button => {
            button.addEventListener('click', (e) => {
                const action = e.target.getAttribute('data-action');
                this.performBulkAction(action);
            });
        });
    }

    updateBulkActions() {
        const selectedVMs = document.querySelectorAll('.vm-checkbox:checked');
        const bulkActionsContainer = document.querySelector('.bulk-actions');
        
        if (bulkActionsContainer) {
            bulkActionsContainer.style.display = selectedVMs.length > 0 ? 'block' : 'none';
        }

        // Update select all checkbox state
        const selectAllCheckbox = document.getElementById('selectAllVMs');
        const allCheckboxes = document.querySelectorAll('.vm-checkbox');
        if (selectAllCheckbox && allCheckboxes.length > 0) {
            selectAllCheckbox.checked = selectedVMs.length === allCheckboxes.length;
            selectAllCheckbox.indeterminate = selectedVMs.length > 0 && selectedVMs.length < allCheckboxes.length;
        }
    }

    async performBulkAction(action) {
        const selectedVMs = Array.from(document.querySelectorAll('.vm-checkbox:checked'))
            .map(checkbox => checkbox.getAttribute('data-vm-id'));

        if (selectedVMs.length === 0) {
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
                    vm_ids: selectedVMs
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message || 'Bulk action completed successfully', 'success');
                // Refresh the page
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

    // VM Console functionality
    openVMConsole(vmId) {
        const consoleWindow = window.open(
            `console.php?vm_id=${vmId}`,
            `console_${vmId}`,
            'width=800,height=600,scrollbars=yes,resizable=yes'
        );
        
        if (consoleWindow) {
            consoleWindow.focus();
        } else {
            this.showNotification('Please allow popups for this site to open the console', 'warning');
        }
    }

    // VM Monitoring
    startVMMonitoring(vmId) {
        const monitoringInterval = setInterval(async () => {
            try {
                const response = await fetch(`api/vm-monitor.php?vm_id=${vmId}`);
                const data = await response.json();

                if (data.success) {
                    this.updateVMMonitoringData(vmId, data.monitoring);
                }
            } catch (error) {
                console.error('Failed to update VM monitoring:', error);
                clearInterval(monitoringInterval);
            }
        }, 2000);

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

    updateVMMonitoringData(vmId, monitoringData) {
        const vmCard = document.querySelector(`[data-vm-id="${vmId}"]`);
        if (!vmCard) return;

        // Update performance indicators
        const performanceContainer = vmCard.querySelector('.vm-performance');
        if (performanceContainer) {
            // Update CPU
            const cpuValue = performanceContainer.querySelector('.performance-item:nth-child(1) .value');
            const cpuBar = performanceContainer.querySelector('.performance-item:nth-child(1) .bar-fill');
            if (cpuValue) cpuValue.textContent = monitoringData.cpu + '%';
            if (cpuBar) cpuBar.style.width = monitoringData.cpu + '%';

            // Update Memory
            const memoryValue = performanceContainer.querySelector('.performance-item:nth-child(2) .value');
            const memoryBar = performanceContainer.querySelector('.performance-item:nth-child(2) .bar-fill');
            if (memoryValue) memoryValue.textContent = monitoringData.memory + '%';
            if (memoryBar) memoryBar.style.width = monitoringData.memory + '%';

            // Update Disk
            const diskValue = performanceContainer.querySelector('.performance-item:nth-child(3) .value');
            const diskBar = performanceContainer.querySelector('.performance-item:nth-child(3) .bar-fill');
            if (diskValue) diskValue.textContent = monitoringData.disk + '%';
            if (diskBar) diskBar.style.width = monitoringData.disk + '%';
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
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }

        // Clear all VM monitoring intervals
        if (window.vmMonitoringIntervals) {
            Object.values(window.vmMonitoringIntervals).forEach(interval => {
                clearInterval(interval);
            });
            window.vmMonitoringIntervals = {};
        }
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboardManager = new DashboardManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.dashboardManager) {
        window.dashboardManager.destroy();
    }
});

// Global functions for backward compatibility
function openVMConsole(vmId) {
    if (window.dashboardManager) {
        window.dashboardManager.openVMConsole(vmId);
    }
}

function startVMMonitoring(vmId) {
    if (window.dashboardManager) {
        window.dashboardManager.startVMMonitoring(vmId);
    }
}

function stopVMMonitoring(vmId) {
    if (window.dashboardManager) {
        window.dashboardManager.stopVMMonitoring(vmId);
    }
}