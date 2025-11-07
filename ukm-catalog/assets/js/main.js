// Main JavaScript for UKM Catalog
class UKMCatalog {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initFlashMessages();
        this.initCart();
    }

    setupEventListeners() {
        // Form validation
        document.addEventListener('submit', this.handleFormSubmit.bind(this));
        
        // Cart functionality
        document.addEventListener('click', this.handleCartActions.bind(this));
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', this.handleSearch.bind(this));
        }

        // Quantity controls
        document.addEventListener('click', this.handleQuantityControls.bind(this));

        // Auto-hide alerts
        this.autoHideAlerts();
    }

    handleFormSubmit(e) {
        const form = e.target;
        if (form.classList.contains('needs-validation')) {
            if (!this.validateForm(form)) {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    }

    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('.form-control');

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';

        // Clear previous errors
        this.clearFieldError(field);

        // Required validation
        if (field.hasAttribute('required') && !value) {
            message = 'This field is required';
            isValid = false;
        }

        // Email validation
        if (field.type === 'email' && value && !this.isValidEmail(value)) {
            message = 'Please enter a valid email address';
            isValid = false;
        }

        // Phone validation
        if (field.name === 'phone' && value && !this.isValidPhone(value)) {
            message = 'Please enter a valid phone number';
            isValid = false;
        }

        // Password confirmation
        if (field.name === 'confirm_password') {
            const password = document.querySelector('input[name="password"]').value;
            if (value !== password) {
                message = 'Passwords do not match';
                isValid = false;
            }
        }

        if (!isValid) {
            this.showFieldError(field, message);
        }

        return isValid;
    }

    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }

    showFieldError(field, message) {
        field.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        field.parentNode.appendChild(feedback);
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        const phoneRegex = /^[0-9]{10,13}$/;
        return phoneRegex.test(phone);
    }

    initFlashMessages() {
        // Auto-hide flash messages after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                this.fadeOut(alert);
            }, 5000);
        });
    }

    autoHideAlerts() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                this.fadeOut(alert);
            }, 5000);
        });
    }

    fadeOut(element) {
        let opacity = 1;
        const timer = setInterval(() => {
            if (opacity <= 0.1) {
                clearInterval(timer);
                element.style.display = 'none';
            }
            element.style.opacity = opacity;
            opacity -= 0.1;
        }, 50);
    }

    initCart() {
        this.updateCartCount();
    }

    handleCartActions(e) {
        if (e.target.classList.contains('add-to-cart')) {
            e.preventDefault();
            const productId = e.target.dataset.productId;
            const quantity = parseInt(e.target.dataset.quantity) || 1;
            this.addToCart(productId, quantity);
        }

        if (e.target.classList.contains('remove-from-cart')) {
            e.preventDefault();
            const productId = e.target.dataset.productId;
            this.removeFromCart(productId);
        }
    }

    async addToCart(productId, quantity = 1) {
        try {
            const response = await fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: quantity
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification('Product added to cart!', 'success');
                this.updateCartCount();
            } else {
                this.showNotification(data.message || 'Failed to add product', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('An error occurred', 'error');
        }
    }

    async removeFromCart(productId) {
        try {
            const response = await fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove',
                    product_id: productId
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification('Product removed from cart', 'success');
                this.updateCartCount();
                // Reload page if on cart page
                if (window.location.pathname.includes('cart.php')) {
                    window.location.reload();
                }
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('An error occurred', 'error');
        }
    }

    updateCartCount() {
        const cartCountElements = document.querySelectorAll('.cart-count');
        
        fetch('api/cart.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cartCountElements.forEach(element => {
                        element.textContent = data.count;
                        element.style.display = data.count > 0 ? 'inline' : 'none';
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching cart count:', error);
            });
    }

    handleQuantityControls(e) {
        if (e.target.classList.contains('quantity-btn')) {
            const input = e.target.parentNode.querySelector('.quantity-input');
            const currentValue = parseInt(input.value) || 1;
            
            if (e.target.textContent === '+') {
                input.value = currentValue + 1;
            } else if (e.target.textContent === '-' && currentValue > 1) {
                input.value = currentValue - 1;
            }

            // Update cart if on cart page
            if (window.location.pathname.includes('cart.php')) {
                const productId = input.dataset.productId;
                this.updateCartQuantity(productId, parseInt(input.value));
            }
        }
    }

    async updateCartQuantity(productId, quantity) {
        try {
            const response = await fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update',
                    product_id: productId,
                    quantity: quantity
                })
            });

            const data = await response.json();

            if (data.success) {
                window.location.reload(); // Reload to update totals
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    handleSearch(e) {
        const searchTerm = e.target.value.toLowerCase();
        const products = document.querySelectorAll('.product-card');

        products.forEach(product => {
            const title = product.querySelector('.product-title').textContent.toLowerCase();
            const description = product.querySelector('.product-description')?.textContent.toLowerCase() || '';
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                product.style.display = 'block';
            } else {
                product.style.display = 'none';
            }
        });
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.textContent = message;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '250px';

        document.body.appendChild(notification);

        // Auto remove after 3 seconds
        setTimeout(() => {
            this.fadeOut(notification);
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 500);
        }, 3000);
    }

    // Admin functions
    confirmDelete(message = 'Are you sure you want to delete this item?') {
        return confirm(message);
    }

    async exportData(type) {
        try {
            const response = await fetch(`api/export.php?type=${type}`);
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `export_${type}_${new Date().toISOString().split('T')[0]}.${type}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Export error:', error);
            this.showNotification('Export failed', 'error');
        }
    }

    // Image preview
    previewImage(input, previewElement) {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewElement.src = e.target.result;
                previewElement.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    new UKMCatalog();
});

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR'
    }).format(amount);
}

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