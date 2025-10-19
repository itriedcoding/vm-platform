// Authentication page JavaScript functionality

class AuthManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupFormValidation();
        this.setupPasswordStrength();
        this.setupPasswordToggle();
        this.setupFormSubmission();
    }

    setupFormValidation() {
        const forms = document.querySelectorAll('.auth-form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    validateForm(form) {
        const inputs = form.querySelectorAll('input[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.showFieldError(input, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(input);
            }
        });

        // Validate email
        const emailInput = form.querySelector('input[type="email"]');
        if (emailInput && emailInput.value) {
            if (!this.isValidEmail(emailInput.value)) {
                this.showFieldError(emailInput, 'Please enter a valid email address');
                isValid = false;
            }
        }

        // Validate password confirmation
        const passwordInput = form.querySelector('input[name="reg_password"]');
        const confirmPasswordInput = form.querySelector('input[name="reg_confirm_password"]');
        
        if (passwordInput && confirmPasswordInput) {
            if (passwordInput.value !== confirmPasswordInput.value) {
                this.showFieldError(confirmPasswordInput, 'Passwords do not match');
                isValid = false;
            }
        }

        // Validate password strength
        if (passwordInput && passwordInput.value) {
            const strength = this.calculatePasswordStrength(passwordInput.value);
            if (strength < 2) {
                this.showFieldError(passwordInput, 'Password is too weak');
                isValid = false;
            }
        }

        return isValid;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    showFieldError(input, message) {
        this.clearFieldError(input);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
        `;
        
        input.parentNode.appendChild(errorDiv);
        input.style.borderColor = '#dc3545';
    }

    clearFieldError(input) {
        const existingError = input.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        input.style.borderColor = '#e9ecef';
    }

    setupPasswordStrength() {
        const passwordInput = document.getElementById('reg_password');
        if (!passwordInput) return;

        passwordInput.addEventListener('input', () => {
            this.updatePasswordStrength(passwordInput.value);
        });
    }

    updatePasswordStrength(password) {
        const strength = this.calculatePasswordStrength(password);
        const strengthFill = document.querySelector('.strength-fill');
        const strengthText = document.querySelector('.strength-text');

        if (!strengthFill || !strengthText) return;

        // Remove all strength classes
        strengthFill.classList.remove('weak', 'fair', 'good', 'strong');
        
        // Add appropriate class and update text
        switch (strength) {
            case 0:
            case 1:
                strengthFill.classList.add('weak');
                strengthText.textContent = 'Weak';
                break;
            case 2:
                strengthFill.classList.add('fair');
                strengthText.textContent = 'Fair';
                break;
            case 3:
                strengthFill.classList.add('good');
                strengthText.textContent = 'Good';
                break;
            case 4:
                strengthFill.classList.add('strong');
                strengthText.textContent = 'Strong';
                break;
        }
    }

    calculatePasswordStrength(password) {
        let strength = 0;
        
        // Length check
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        
        // Character variety checks
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        // Cap at 4 for display purposes
        return Math.min(strength, 4);
    }

    setupPasswordToggle() {
        const toggleButtons = document.querySelectorAll('.password-toggle');
        toggleButtons.forEach(button => {
            button.addEventListener('click', () => {
                const input = button.parentNode.querySelector('input');
                const icon = button.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }

    setupFormSubmission() {
        const forms = document.querySelectorAll('.auth-form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    this.setLoadingState(submitButton, true);
                }
            });
        });
    }

    setLoadingState(button, isLoading) {
        if (isLoading) {
            button.disabled = true;
            button.classList.add('loading');
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        } else {
            button.disabled = false;
            button.classList.remove('loading');
            button.innerHTML = button.dataset.originalText || button.innerHTML;
        }
    }
}

// Tab switching functionality
function switchTab(tabName) {
    // Update tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase().includes(tabName)) {
            btn.classList.add('active');
        }
    });

    // Update forms
    const forms = document.querySelectorAll('.auth-form');
    forms.forEach(form => {
        form.classList.remove('active');
        if (form.id === tabName + 'Form') {
            form.classList.add('active');
        }
    });

    // Clear any existing errors
    const errors = document.querySelectorAll('.field-error');
    errors.forEach(error => error.remove());

    // Reset form styles
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.style.borderColor = '#e9ecef';
    });
}

// Password toggle functionality
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentNode.querySelector('.password-toggle');
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Form validation on input
document.addEventListener('input', (e) => {
    if (e.target.matches('input[type="email"]')) {
        const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e.target.value);
        if (e.target.value && !isValid) {
            e.target.style.borderColor = '#dc3545';
        } else {
            e.target.style.borderColor = '#e9ecef';
        }
    }
});

// Password confirmation validation
document.addEventListener('input', (e) => {
    if (e.target.name === 'reg_confirm_password') {
        const passwordInput = document.querySelector('input[name="reg_password"]');
        if (passwordInput && e.target.value !== passwordInput.value) {
            e.target.style.borderColor = '#dc3545';
        } else {
            e.target.style.borderColor = '#e9ecef';
        }
    }
});

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AuthManager();
});

// Auto-focus first input
document.addEventListener('DOMContentLoaded', () => {
    const firstInput = document.querySelector('.auth-form.active input');
    if (firstInput) {
        firstInput.focus();
    }
});

// Handle Enter key in forms
document.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        const activeForm = document.querySelector('.auth-form.active');
        if (activeForm) {
            const submitButton = activeForm.querySelector('button[type="submit"]');
            if (submitButton && !submitButton.disabled) {
                submitButton.click();
            }
        }
    }
});