(function () {
    function buildModal() {
        var overlay = document.createElement('div');
        overlay.className = 'admin-confirm-overlay';
        overlay.innerHTML = [
            '<div class="admin-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-confirm-title">',
            '  <div class="admin-confirm-icon"><i data-lucide="alert-triangle"></i></div>',
            '  <div class="admin-confirm-content">',
            '    <h3 id="admin-confirm-title">Xác nhận thao tác</h3>',
            '    <p id="admin-confirm-message"></p>',
            '  </div>',
            '  <div class="admin-confirm-actions">',
            '    <button type="button" class="btn btn-outline admin-confirm-cancel">Hủy</button>',
            '    <button type="button" class="btn btn-danger admin-confirm-ok">Xác nhận</button>',
            '  </div>',
            '</div>'
        ].join('');
        document.body.appendChild(overlay);
        if (window.lucide) {
            window.lucide.createIcons();
        }
        return overlay;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var overlay = buildModal();
        var messageEl = overlay.querySelector('#admin-confirm-message');
        var okBtn = overlay.querySelector('.admin-confirm-ok');
        var cancelBtn = overlay.querySelector('.admin-confirm-cancel');
        var pendingAction = null;

        function closeModal() {
            overlay.classList.remove('is-open');
            pendingAction = null;
        }

        function openModal(message, action) {
            messageEl.textContent = message || 'Bạn có chắc chắn muốn thực hiện thao tác này?';
            pendingAction = action;
            overlay.classList.add('is-open');
            cancelBtn.focus();
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('a[data-confirm]');
            if (!trigger) return;

            event.preventDefault();
            openModal(trigger.getAttribute('data-confirm'), function () {
                window.location.href = trigger.href;
            });
        });

        document.addEventListener('submit', function (event) {
            var form = event.target.closest('form[data-confirm]');
            if (!form || form.dataset.confirmed === '1') return;

            event.preventDefault();
            var submitter = event.submitter;
            openModal(form.getAttribute('data-confirm'), function () {
                if (submitter && submitter.name) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = submitter.name;
                    hidden.value = submitter.value;
                    form.appendChild(hidden);
                }
                form.dataset.confirmed = '1';
                form.submit();
            });
        });

        okBtn.addEventListener('click', function () {
            var action = pendingAction;
            closeModal();
            if (action) action();
        });

        cancelBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) closeModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
                closeModal();
            }
        });
    });
})();
