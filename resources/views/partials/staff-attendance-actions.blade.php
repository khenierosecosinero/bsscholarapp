<div class="staff-doc-review-actions">
    @if($attendance->hasPhoto())
        <a href="{{ route('staff.attendances.photo', $attendance) }}" target="_blank" rel="noopener" class="staff-btn staff-btn-sm">View</a>
    @endif
@if($attendance->isReadyForVerification())
        <form
            method="POST"
            action="{{ route('staff.attendances.approve', $attendance) }}"
            data-confirm="Service hours will be credited to this scholar."
            data-confirm-title="Approve attendance hours?"
            data-confirm-yes="Approve"
            data-confirm-no="Cancel"
        >
            @csrf
            <button type="submit" class="staff-btn staff-btn-sm staff-btn-success">Approve</button>
        </form>
        <form
            method="POST"
            action="{{ route('staff.attendances.reject', $attendance) }}"
            class="staff-reject-form"
            data-confirm="The scholar can upload a new photo after rejection."
            data-confirm-title="Reject this attendance?"
            data-confirm-yes="Reject"
            data-confirm-no="Cancel"
            data-confirm-variant="danger"
        >
            @csrf
            <input type="text" name="remarks" class="staff-review-notes" placeholder="Reason (optional)" maxlength="500">
            <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Reject</button>
        </form>
@elseif($attendance->status === 'pending' && $attendance->hasCheckedIn() && ! $attendance->hasPhoto())
    <span class="staff-muted">Waiting for photo</span>
@elseif($attendance->status === 'pending' && $attendance->hasCheckedIn() && ! $attendance->check_out)
    <span class="staff-muted">Waiting for check-out</span>
@endif
</div>
