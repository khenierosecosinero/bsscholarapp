<div class="staff-confirm-modal" id="admin-staff-more-modal" aria-hidden="true">
    <div class="staff-confirm-backdrop" data-staff-more-close></div>
    <div class="staff-confirm-dialog staff-more-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-staff-more-title">
        <div class="staff-confirm-icon" aria-hidden="true">⚙</div>
        <h3 id="admin-staff-more-title" class="staff-confirm-title">Manage Scholar Staff</h3>
        <p class="staff-confirm-name" id="admin-staff-more-name" hidden></p>
        <p class="staff-confirm-message">Choose an action for this Scholar Staff account.</p>
        <div class="staff-more-actions">
            <button type="button" class="staff-btn staff-btn-danger" data-staff-more-block hidden>Block</button>
            <button type="button" class="staff-btn staff-btn-warning" data-staff-more-suspend hidden>Suspend</button>
            <button type="button" class="staff-btn staff-btn-success" data-staff-more-activate>Activate</button>
            <button type="button" class="staff-btn staff-btn-danger" data-staff-more-delete>Delete</button>
        </div>
        <div class="staff-confirm-actions">
            <button type="button" class="staff-btn staff-confirm-no" data-staff-more-close>Close</button>
        </div>
    </div>
</div>

<form
    id="admin-staff-block-form"
    method="POST"
    hidden
    data-no-loading="true"
    data-confirm="The account will be blocked and will not be able to log in or use the system until it is reactivated."
    data-confirm-title="Block this scholar staff account?"
    data-confirm-yes="Block"
    data-confirm-no="Cancel"
    data-confirm-variant="danger"
>
    @csrf
    @include('partials.admin-staff-return-fields')
</form>
<form
    id="admin-staff-suspend-form"
    method="POST"
    hidden
    data-no-loading="true"
    data-confirm="The account will be suspended and will not be able to log in or use the system until it is reactivated."
    data-confirm-title="Suspend this scholar staff account?"
    data-confirm-yes="Suspend"
    data-confirm-no="Cancel"
    data-confirm-variant="danger"
>
    @csrf
    @include('partials.admin-staff-return-fields')
</form>
<form id="admin-staff-activate-form" method="POST" hidden data-no-loading="true">
    @csrf
    @include('partials.admin-staff-return-fields')
</form>
<form
    id="admin-staff-delete-form"
    method="POST"
    hidden
    data-no-loading="true"
    data-confirm="This permanently removes the Scholar Staff account and cannot be undone."
    data-confirm-title="Delete this scholar staff account?"
    data-confirm-yes="Delete"
    data-confirm-no="Cancel"
    data-confirm-variant="danger"
>
    @csrf
    @method('DELETE')
    @include('partials.admin-staff-return-fields')
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('admin-staff-more-modal');
    if (!modal) return;

    var nameEl = document.getElementById('admin-staff-more-name');
    var blockForm = document.getElementById('admin-staff-block-form');
    var suspendForm = document.getElementById('admin-staff-suspend-form');
    var activateForm = document.getElementById('admin-staff-activate-form');
    var deleteForm = document.getElementById('admin-staff-delete-form');
    var blockBtn = modal.querySelector('[data-staff-more-block]');
    var suspendBtn = modal.querySelector('[data-staff-more-suspend]');
    var activateBtn = modal.querySelector('[data-staff-more-activate]');

    function openMore(button) {
        var name = (button.getAttribute('data-staff-name') || '').trim();
        var status = button.getAttribute('data-account-status') || '';
        if (nameEl) {
            nameEl.hidden = !name;
            nameEl.textContent = name;
        }
        [blockForm, suspendForm, activateForm, deleteForm].forEach(function (form) {
            if (!form) return;
            if (name) form.setAttribute('data-confirm-name', name);
        });
        function setFormAction(form, url) {
            if (!form) return;
            if (url) {
                form.setAttribute('action', url);
            } else {
                form.removeAttribute('action');
            }
        }
        function showButton(buttonEl, visible) {
            if (buttonEl) buttonEl.hidden = !visible;
        }
        setFormAction(blockForm, button.getAttribute('data-block-url') || '');
        setFormAction(suspendForm, button.getAttribute('data-suspend-url') || '');
        setFormAction(activateForm, button.getAttribute('data-activate-url') || '');
        setFormAction(deleteForm, button.getAttribute('data-delete-url') || '');
        showButton(blockBtn, status === 'approved' || status === 'suspended');
        showButton(suspendBtn, status === 'approved' || status === 'blocked');
        showButton(activateBtn, status !== 'approved' && status !== 'blocked' && status !== 'suspended');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeMore() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('[data-staff-more]').forEach(function (button) {
        button.addEventListener('click', function () {
            openMore(button);
        });
    });

    modal.querySelectorAll('[data-staff-more-close]').forEach(function (trigger) {
        trigger.addEventListener('click', closeMore);
    });

    function confirmForm(form) {
        if (!form || !form.getAttribute('action')) return;
        closeMore();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    }

    if (blockBtn) {
        blockBtn.addEventListener('click', function () {
            confirmForm(blockForm);
        });
    }

    if (suspendBtn) {
        suspendBtn.addEventListener('click', function () {
            confirmForm(suspendForm);
        });
    }

    if (activateBtn) {
        activateBtn.addEventListener('click', function () {
            if (!activateForm || !activateForm.getAttribute('action')) return;
            activateForm.submit();
        });
    }

    var deleteBtn = modal.querySelector('[data-staff-more-delete]');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            if (!deleteForm || !deleteForm.getAttribute('action')) return;
            closeMore();
            if (typeof deleteForm.requestSubmit === 'function') {
                deleteForm.requestSubmit();
            } else {
                deleteForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeMore();
        }
    });
});
</script>
@endpush
