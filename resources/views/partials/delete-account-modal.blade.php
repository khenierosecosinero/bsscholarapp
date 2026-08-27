<div
    id="delete-account-modal"
    class="delete-account-modal"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="delete-account-modal-title"
    aria-describedby="delete-account-modal-description"
>
    <div class="delete-account-modal-backdrop" aria-hidden="true"></div>
    <div class="delete-account-modal-panel">
        <div class="delete-account-modal-icon" aria-hidden="true">&#9888;</div>
        <h2 id="delete-account-modal-title" class="delete-account-modal-title">
            Are you sure you want to permanently delete your account?
        </h2>
        <p id="delete-account-modal-description" class="delete-account-modal-description">
            This action cannot be undone. All of your account information, data, records, activities, and associated information will be permanently deleted from the database.
        </p>
        <div class="delete-account-modal-actions">
            <button type="button" class="btn danger filled" id="delete-account-confirm">
                Yes, Delete My Account
            </button>
            <button type="button" class="btn outline neutral" id="delete-account-cancel">
                No, Keep My Account
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('delete-account-modal');
    var form = document.getElementById('delete-account-form');
    var trigger = document.getElementById('delete-account-trigger');
    var confirmBtn = document.getElementById('delete-account-confirm');
    var cancelBtn = document.getElementById('delete-account-cancel');
    var passwordInput = document.getElementById('delete_password');

    if (!modal || !form || !trigger || !confirmBtn || !cancelBtn || !passwordInput) {
        return;
    }

    var lastFocusedElement = null;
    var focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function getFocusableElements() {
        return Array.prototype.slice.call(modal.querySelectorAll(focusableSelector));
    }

    function openModal() {
        lastFocusedElement = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('delete-account-modal-open');
        cancelBtn.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('delete-account-modal-open');
        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
        }
    }

    trigger.addEventListener('click', function () {
        if (!passwordInput.reportValidity()) {
            return;
        }

        openModal();
    });

    cancelBtn.addEventListener('click', closeModal);

    confirmBtn.addEventListener('click', function () {
        if (!passwordInput.reportValidity()) {
            closeModal();
            passwordInput.focus();
            return;
        }

        confirmBtn.disabled = true;
        cancelBtn.disabled = true;
        form.submit();
    });

    modal.addEventListener('keydown', function (event) {
        if (modal.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        var focusable = getFocusableElements();
        if (!focusable.length) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
});
</script>
