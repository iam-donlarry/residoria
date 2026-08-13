/* assets/js/modal.js - Offcanvas Slide-Over Engine (Half-Screen 50vw Drawers) */

(function() {
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            if (window.lucide) window.lucide.createIcons();
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    window.openOffcanvas = function(panelId) {
        window.openModal(panelId);
    };

    window.closeOffcanvas = function(panelId) {
        window.closeModal(panelId);
    };

    // Global listener to close Offcanvas on backdrop click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay') || e.target.classList.contains('offcanvas-overlay')) {
            e.target.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Custom Confirmation Dialog
    window.confirmAction = function(options) {
        const title = options.title || 'Confirm Action';
        const message = options.message || 'Are you sure you want to proceed?';
        const confirmBtnText = options.confirmText || 'Confirm';
        const cancelBtnText = options.cancelText || 'Cancel';
        const isDanger = options.danger || false;

        let confirmModal = document.getElementById('global-confirm-modal');
        if (!confirmModal) {
            confirmModal = document.createElement('div');
            confirmModal.id = 'global-confirm-modal';
            confirmModal.className = 'modal-overlay';
            confirmModal.innerHTML = `
                <div class="modal-container">
                    <div class="modal-header">
                        <h3 id="confirm-modal-title">Confirm Action</h3>
                        <button class="toast-close" onclick="closeModal('global-confirm-modal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p id="confirm-modal-message">Are you sure?</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="closeModal('global-confirm-modal')">${cancelBtnText}</button>
                        <button id="confirm-modal-btn" class="btn ${isDanger ? 'btn-danger' : 'btn-primary'}">${confirmBtnText}</button>
                    </div>
                </div>
            `;
            document.body.appendChild(confirmModal);
        }

        document.getElementById('confirm-modal-title').innerText = title;
        document.getElementById('confirm-modal-message').innerText = message;
        
        const confirmBtn = document.getElementById('confirm-modal-btn');
        confirmBtn.className = `btn ${isDanger ? 'btn-danger' : 'btn-primary'}`;
        confirmBtn.innerText = confirmBtnText;

        confirmBtn.onclick = function() {
            closeModal('global-confirm-modal');
            if (typeof options.onConfirm === 'function') {
                options.onConfirm();
            }
        };

        openModal('global-confirm-modal');
    };
})();
