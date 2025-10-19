// Main JavaScript functionality for VM Platform

class VMPlatform {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeTooltips();
        this.setupAutoRefresh();
    }

    setupEventListeners() {
        // Modal handling
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal(e.target);
            }
            if (e.target.classList.contains('close')) {
                this.closeModal(e.target.closest('.modal'));
            }
        });

        // Form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'createVMForm') {
                e.preventDefault();
                this.createVM();
            }
        });

        // Button actions
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action]')) {
                const action = e.target.getAttribute('data-action');
                const vmId = e.target.getAttribute('data-vm-id');
                this.handleVMAction(action, vmId);
            }
        });
    }

    initializeTooltips() {
        // Simple tooltip implementation
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', this.showTooltip);
            element.addEventListener('mouseleave', this.hideTooltip);
        });
    }

    showTooltip(e) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = e.target.getAttribute('data-tooltip');
        tooltip.style.cssText = `
            position: absolute;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
        `;
        
        document.body.appendChild(tooltip);
        
        const rect = e.target.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        e.target._tooltip = tooltip;
    }

    hideTooltip(e) {
        if (e.target._tooltip) {
            e.target._tooltip.remove();
            e.target._tooltip = null;
        }
    }

    setupAutoRefresh() {
        // Auto-refresh dashboard every 30 seconds
        if (window.location.pathname.includes('dashboard.php')) {
            setInterval(() => {
                this.refreshDashboard();
            }, 30000);
        }
    }

    async refreshDashboard() {
        try {
            const response = await fetch('api/dashboard-stats.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardStats(data.stats);
            }
        } catch (error) {
            console.error('Failed to refresh dashboard:', error);
        }
    }

    updateDashboardStats(stats) {
        // Update stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            const statType = card.querySelector('.stat-content p').textContent.toLowerCase();
            const valueElement = card.querySelector('.stat-content h3');
            
            switch (statType) {
                case 'total vms':
                    valueElement.textContent = stats.totalVMs;
                    break;
                case 'running vms':
                    valueElement.textContent = stats.runningVMs;
                    break;
                case 'total memory':
                    valueElement.textContent = stats.totalMemory + 'GB';
                    break;
                case 'total cpus':
                    valueElement.textContent = stats.totalCPUs;
                    break;
            }
        });
    }

    // Modal functions
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(modal) {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // VM Management functions
    async createVM() {
        const form = document.getElementById('createVMForm');
        const formData = new FormData(form);
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="button"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loading"></span> Creating...';
        submitBtn.disabled = true;

        try {
            const response = await fetch('api/create-vm.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('VM created successfully!', 'success');
                this.closeModal(document.getElementById('createVMModal'));
                form.reset();
                // Refresh the page to show new VM
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showNotification(result.message || 'Failed to create VM', 'error');
            }
        } catch (error) {
            console.error('Error creating VM:', error);
            this.showNotification('An error occurred while creating the VM', 'error');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }

    async handleVMAction(action, vmId) {
        if (!vmId) {
            this.showNotification('VM ID not found', 'error');
            return;
        }

        const actionMap = {
            'start': 'Starting VM...',
            'stop': 'Stopping VM...',
            'restart': 'Restarting VM...',
            'delete': 'Deleting VM...',
            'snapshot': 'Creating snapshot...',
            'backup': 'Creating backup...'
        };

        this.showNotification(actionMap[action] || 'Processing...', 'warning');

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
                // Refresh the page to show updated status
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showNotification(result.message || 'Action failed', 'error');
            }
        } catch (error) {
            console.error('Error performing VM action:', error);
            this.showNotification('An error occurred while performing the action', 'error');
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

    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    formatUptime(seconds) {
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        
        if (days > 0) {
            return `${days}d ${hours}h ${minutes}m`;
        } else if (hours > 0) {
            return `${hours}h ${minutes}m`;
        } else {
            return `${minutes}m`;
        }
    }

    // API helper functions
    async apiCall(endpoint, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(endpoint, options);
            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    }
}

// Global functions for backward compatibility
function openCreateVMModal() {
    const platform = new VMPlatform();
    platform.openModal('createVMModal');
}

function closeCreateVMModal() {
    const platform = new VMPlatform();
    platform.closeModal(document.getElementById('createVMModal'));
}

function createVM() {
    const platform = new VMPlatform();
    platform.createVM();
}

function vmAction(vmId, action) {
    const platform = new VMPlatform();
    platform.handleVMAction(action, vmId);
}

// Initialize platform when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new VMPlatform();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VMPlatform;
}