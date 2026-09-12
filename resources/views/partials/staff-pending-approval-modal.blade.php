@if(!empty($showPendingStaffApprovalModal))
<div
    id="staff-pending-approval-modal"
    class="pending-approval-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="staff-pending-approval-modal-title"
    aria-describedby="staff-pending-approval-modal-description"
>
    <div class="pending-approval-modal-backdrop" aria-hidden="true"></div>
    <div class="pending-approval-modal-panel">
        <div class="pending-approval-modal-icon" aria-hidden="true">!</div>
        <h2 id="staff-pending-approval-modal-title" class="pending-approval-modal-title">
            Account Pending Admin Approval
        </h2>
        <p id="staff-pending-approval-modal-description" class="pending-approval-modal-description">
            Your scholar staff account has been successfully created and is currently waiting for approval from a system administrator. You cannot access the Scholar Staff dashboard, sections, or features until your account is approved.
        </p>
        <div class="pending-approval-modal-actions">
            <button type="button" class="btn filled" id="staff-pending-approval-dismiss">
                OK, Got It
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('staff-pending-approval-modal');
    var dismissBtn = document.getElementById('staff-pending-approval-dismiss');
    var backdrop = modal ? modal.querySelector('.pending-approval-modal-backdrop') : null;
    var csrf = document.querySelector('meta[name="csrf-token"]');

    if (!modal || !dismissBtn) {
        return;
    }

    document.body.classList.add('pending-approval-modal-open');
    dismissBtn.focus();

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('pending-approval-modal-open');
    }

    function dismissModal() {
        fetch('{{ route('staff.dismiss-pending-modal') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                'Accept': 'application/json',
            },
        }).finally(closeModal);
    }

    dismissBtn.addEventListener('click', dismissModal);

    if (backdrop) {
        backdrop.addEventListener('click', dismissModal);
    }

    modal.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            dismissModal();
        }
    });
});
</script>
@endif
