/* assets/js/toast.js - Centralized Toast Notification Engine */

(function() {
    function createToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    window.showToast = function(message, type = 'success', duration = 4000) {
        const container = createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        let iconName = 'check-circle';
        if (type === 'error') iconName = 'alert-circle';
        if (type === 'warning') iconName = 'alert-triangle';
        if (type === 'info') iconName = 'info';

        toast.innerHTML = `
            <div class="toast-icon">
                <i data-lucide="${iconName}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;

        container.appendChild(toast);

        // Re-initialize Lucide Icons for dynamic element
        if (window.lucide) {
            window.lucide.createIcons();
        }

        // Trigger transition
        setTimeout(() => toast.classList.add('show'), 10);

        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    };
})();
