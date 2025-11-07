// Debug and Error Handling Utilities for UKM Catalog
class UKMDebugger {
    constructor() {
        this.errors = [];
        this.warnings = [];
        this.init();
    }

    init() {
        // Override console methods to capture errors
        this.overrideConsole();
        
        // Global error handler
        window.addEventListener('error', this.handleGlobalError.bind(this));
        
        // Unhandled promise rejection handler
        window.addEventListener('unhandledrejection', this.handlePromiseRejection.bind(this));
        
        // Debug mode check
        this.debugMode = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    }

    overrideConsole() {
        const originalError = console.error;
        const originalWarn = console.warn;

        console.error = (...args) => {
            this.errors.push({
                type: 'error',
                message: args.join(' '),
                timestamp: new Date().toISOString(),
                stack: new Error().stack
            });
            originalError.apply(console, args);
        };

        console.warn = (...args) => {
            this.warnings.push({
                type: 'warning',
                message: args.join(' '),
                timestamp: new Date().toISOString()
            });
            originalWarn.apply(console, args);
        };
    }

    handleGlobalError(event) {
        this.errors.push({
            type: 'global_error',
            message: event.error?.message || event.message,
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno,
            error: event.error,
            timestamp: new Date().toISOString()
        });

        // Show user-friendly error message
        if (!this.debugMode) {
            event.preventDefault();
            this.showUserFriendlyError();
        }
    }

    handlePromiseRejection(event) {
        this.errors.push({
            type: 'promise_rejection',
            message: event.reason?.message || event.reason,
            reason: event.reason,
            timestamp: new Date().toISOString()
        });

        if (!this.debugMode) {
            event.preventDefault();
        }
    }

    showUserFriendlyError() {
        // Remove existing error message
        const existingError = document.getElementById('ukm-error-message');
        if (existingError) {
            existingError.remove();
        }

        // Create error message
        const errorDiv = document.createElement('div');
        errorDiv.id = 'ukm-error-message';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ff4757;
            color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 9999;
            max-width: 300px;
            font-size: 14px;
        `;
        errorDiv.innerHTML = `
            <strong>Terjadi Kesalahan</strong><br>
            Maaf, terjadi kesalahan pada sistem. Silakan coba lagi.
            <button onclick="this.parentElement.remove()" style="float:right;background:none;border:none;color:white;cursor:pointer;">×</button>
        `;

        document.body.appendChild(errorDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.remove();
            }
        }, 5000);
    }

    logError(context, error) {
        this.errors.push({
            type: 'custom_error',
            context: context,
            message: error.message || error,
            stack: error.stack,
            timestamp: new Date().toISOString()
        });

        if (this.debugMode) {
            console.error(`[${context}]`, error);
        }
    }

    logWarning(context, message) {
        this.warnings.push({
            type: 'custom_warning',
            context: context,
            message: message,
            timestamp: new Date().toISOString()
        });

        if (this.debugMode) {
            console.warn(`[${context}]`, message);
        }
    }

    getErrorReport() {
        return {
            errors: this.errors,
            warnings: this.warnings,
            userAgent: navigator.userAgent,
            url: window.location.href,
            timestamp: new Date().toISOString()
        };
    }

    clearLogs() {
        this.errors = [];
        this.warnings = [];
    }
}

// Enhanced error handling for form validation
class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = {};
        this.init();
    }

    init() {
        // Add real-time validation
        const inputs = this.form.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });

        // Add form submission handler
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    validateField(field) {
        const value = field.value.trim();
        const fieldName = field.name;
        let isValid = true;
        let message = '';

        this.clearFieldError(field);

        // Required validation
        if (field.hasAttribute('required') && !value) {
            message = 'This field is required';
            isValid = false;
        }
        // Email validation
        else if (field.type === 'email' && value && !this.isValidEmail(value)) {
            message = 'Please enter a valid email address';
            isValid = false;
        }
        // Phone validation
        else if (fieldName === 'phone' && value && !this.isValidPhone(value)) {
            message = 'Please enter a valid phone number (10-13 digits)';
            isValid = false;
        }
        // Password validation
        else if (fieldName === 'password' && value && value.length < 6) {
            message = 'Password must be at least 6 characters';
            isValid = false;
        }
        // Confirm password validation
        else if (fieldName === 'confirm_password') {
            const password = this.form.querySelector('input[name="password"]')?.value;
            if (value !== password) {
                message = 'Passwords do not match';
                isValid = false;
            }
        }
        // Price validation
        else if (fieldName === 'price' && value && parseFloat(value) <= 0) {
            message = 'Price must be greater than 0';
            isValid = false;
        }
        // Stock validation
        else if (fieldName === 'stock' && value && parseInt(value) < 0) {
            message = 'Stock cannot be negative';
            isValid = false;
        }

        if (!isValid) {
            this.showFieldError(field, message);
            this.errors[fieldName] = message;
        } else {
            delete this.errors[fieldName];
        }

        return isValid;
    }

    showFieldError(field, message) {
        field.classList.add('is-invalid');
        
        let feedback = field.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    }

    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }

    handleSubmit(e) {
        let isFormValid = true;
        const inputs = this.form.querySelectorAll('.form-control');

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isFormValid = false;
            }
        });

        if (!isFormValid) {
            e.preventDefault();
            e.stopPropagation();
            
            // Focus on first error field
            const firstError = this.form.querySelector('.is-invalid');
            if (firstError) {
                firstError.focus();
            }
            
            // Show general error message
            this.showGeneralError('Please correct the errors above');
        }
    }

    showGeneralError(message) {
        // Remove existing general error
        const existingError = this.form.querySelector('.general-error');
        if (existingError) {
            existingError.remove();
        }

        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger general-error mt-3';
        errorDiv.textContent = message;
        
        this.form.appendChild(errorDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.remove();
            }
        }, 5000);
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        const phoneRegex = /^[0-9]{10,13}$/;
        return phoneRegex.test(phone);
    }

    getErrors() {
        return this.errors;
    }
}

// Enhanced cart functionality with error handling
class CartManager {
    constructor() {
        this.isLoading = false;
        this.baseUrl = window.location.origin + window.location.pathname.replace(/[^/]+$/, '');
    }

    async apiRequest(action, data) {
        if (this.isLoading) {
            throw new Error('Request already in progress');
        }

        this.isLoading = true;
        
        try {
            const response = await fetch(this.baseUrl + 'api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: action,
                    ...data
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Unknown error occurred');
            }

            // Update cart count display
            this.updateCartCount(result.count || 0);
            
            return result;
            
        } catch (error) {
            console.error(`Cart API Error (${action}):`, error);
            throw error;
        } finally {
            this.isLoading = false;
        }
    }

    async addToCart(productId, quantity = 1) {
        try {
            const result = await this.apiRequest('add', {
                product_id: productId,
                quantity: quantity
            });
            
            this.showNotification('Product added to cart!', 'success');
            return result;
            
        } catch (error) {
            this.showNotification(error.message || 'Failed to add product to cart', 'error');
            throw error;
        }
    }

    async updateQuantity(productId, quantity) {
        try {
            const result = await this.apiRequest('update', {
                product_id: productId,
                quantity: quantity
            });
            
            return result;
            
        } catch (error) {
            this.showNotification(error.message || 'Failed to update cart', 'error');
            throw error;
        }
    }

    async removeFromCart(productId) {
        try {
            const result = await this.apiRequest('remove', {
                product_id: productId
            });
            
            this.showNotification('Product removed from cart', 'success');
            return result;
            
        } catch (error) {
            this.showNotification(error.message || 'Failed to remove product from cart', 'error');
            throw error;
        }
    }

    async clearCart() {
        try {
            const result = await this.apiRequest('clear', {});
            
            this.showNotification('Cart cleared', 'success');
            return result;
            
        } catch (error) {
            this.showNotification(error.message || 'Failed to clear cart', 'error');
            throw error;
        }
    }

    updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            element.style.display = count > 0 ? 'inline' : 'none';
        });
    }

    showNotification(message, type = 'info') {
        // Remove existing notification
        const existing = document.getElementById('cart-notification');
        if (existing) {
            existing.remove();
        }

        const notification = document.createElement('div');
        notification.id = 'cart-notification';
        notification.className = `alert alert-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 250px;
            animation: slideIn 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        // Auto remove after 3 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => notification.remove(), 300);
            }
        }, 3000);
    }
}

// Initialize debugger
debugger = new UKMDebugger();

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

console.log('UKM Catalog Debug System Loaded');
console.log('Debug Mode:', debugger.debugMode);
console.log('Press Ctrl+Shift+D to toggle debug panel');