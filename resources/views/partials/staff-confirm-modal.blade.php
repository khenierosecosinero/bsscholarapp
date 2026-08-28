<div class="staff-confirm-modal" id="staff-confirm-modal" aria-hidden="true">
    <div class="staff-confirm-backdrop" data-confirm-close></div>
    <div class="staff-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="staff-confirm-title">
        <div class="staff-confirm-icon">!</div>
        <h3 id="staff-confirm-title" class="staff-confirm-title">Confirm action</h3>
        <p class="staff-confirm-message" id="staff-confirm-message"></p>
        <div class="staff-confirm-actions">
            <button type="button" class="staff-btn staff-confirm-no" data-confirm-no>No</button>
            <button type="button" class="staff-btn staff-btn-danger staff-confirm-yes" data-confirm-yes>Yes</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('staff-confirm-modal');
    if (!modal) return;

    var messageEl = modal.querySelector('.staff-confirm-message');
    var yesBtn = modal.querySelector('[data-confirm-yes]');
    var noBtn = modal.querySelector('[data-confirm-no]');
    var closeTriggers = modal.querySelectorAll('[data-confirm-close]');
    var pendingForm = null;

    function openModal(message, form) {
        pendingForm = form;
        messageEl.textContent = message;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        yesBtn.focus();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        pendingForm = null;
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === 'true') {
                form.dataset.confirmed = '';
                return;
            }

            event.preventDefault();
            openModal(form.getAttribute('data-confirm') || 'Are you sure?', form);
        });
    });

    yesBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = 'true';
        pendingForm.submit();
        closeModal();
    });

    noBtn.addEventListener('click', closeModal);

    closeTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
});
</script>
