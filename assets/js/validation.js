/**
 * Validation script for Luna Chatbot
 */

document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('form');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            let hasError = false;
            
            // Get all required inputs
            const requiredInputs = form.querySelectorAll('input[required], textarea[required], select[required]');
            
            // Clear previous error messages
            const errorMessages = form.querySelectorAll('.error-message');
            errorMessages.forEach(function(msg) {
                msg.remove();
            });
            
            // Validate each required field
            requiredInputs.forEach(function(input) {
                if (!input.value.trim()) {
                    hasError = true;
                    
                    // Add error styling
                    input.classList.add('input-error');
                    
                    // Add error message
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'error-message';
                    errorMessage.textContent = 'This field is required';
                    
                    // Insert error message after input
                    input.parentNode.insertBefore(errorMessage, input.nextSibling);
                } else {
                    input.classList.remove('input-error');
                }
            });
            
            // Validate email fields
            const emailInputs = form.querySelectorAll('input[type="email"]');
            emailInputs.forEach(function(input) {
                if (input.value.trim() && !isValidEmail(input.value.trim())) {
                    hasError = true;
                    
                    // Add error styling
                    input.classList.add('input-error');
                    
                    // Add error message
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'error-message';
                    errorMessage.textContent = 'Please enter a valid email address';
                    
                    // Insert error message after input
                    input.parentNode.insertBefore(errorMessage, input.nextSibling);
                }
            });
            
            // Validate number fields
            const numberInputs = form.querySelectorAll('input[type="number"]');
            numberInputs.forEach(function(input) {
                if (input.value.trim()) {
                    const min = parseFloat(input.getAttribute('min'));
                    const max = parseFloat(input.getAttribute('max'));
                    const value = parseFloat(input.value);
                    
                    if ((min !== null && value < min) || (max !== null && value > max)) {
                        hasError = true;
                        
                        // Add error styling
                        input.classList.add('input-error');
                        
                        // Add error message
                        const errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        errorMessage.textContent = `Value must be between ${min} and ${max}`;
                        
                        // Insert error message after input
                        input.parentNode.insertBefore(errorMessage, input.nextSibling);
                    }
                }
            });
            
            // Password confirmation validation
            const passwordInput = form.querySelector('input[name="password"]');
            const confirmPasswordInput = form.querySelector('input[name="confirm_password"]');
            
            if (passwordInput && confirmPasswordInput) {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    hasError = true;
                    
                    // Add error styling
                    confirmPasswordInput.classList.add('input-error');
                    
                    // Add error message
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'error-message';
                    errorMessage.textContent = 'Passwords do not match';
                    
                    // Insert error message after input
                    confirmPasswordInput.parentNode.insertBefore(errorMessage, confirmPasswordInput.nextSibling);
                }
            }
            
            // Prevent form submission if there are errors
            if (hasError) {
                event.preventDefault();
                
                // Scroll to first error
                const firstError = form.querySelector('.input-error');
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        // Clear error styling on input
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(function(input) {
            input.addEventListener('input', function() {
                this.classList.remove('input-error');
                
                // Remove error message
                const errorMessage = this.nextElementSibling;
                if (errorMessage && errorMessage.classList.contains('error-message')) {
                    errorMessage.remove();
                }
            });
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});

/**
 * Validate email format
 * 
 * @param {string} email Email to validate
 * @return {boolean} True if valid, false otherwise
 */
function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

/**
 * Format a number with commas
 * 
 * @param {number} x Number to format
 * @return {string} Formatted number
 */
function numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

/**
 * Truncate a string to a specified length
 * 
 * @param {string} str String to truncate
 * @param {number} length Maximum length
 * @return {string} Truncated string
 */
function truncateString(str, length) {
    if (str.length <= length) {
        return str;
    }
    return str.substring(0, length) + '...';
}

/**
 * Escape HTML special characters
 * 
 * @param {string} html String to escape
 * @return {string} Escaped string
 */
function escapeHtml(html) {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
}