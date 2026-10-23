@php
    $canControl = auth()->user()?->hasStaffPortalAccess();
    $isOpen = $event->isAttendanceOpen();
@endphp

<section class="staff-attendance-session {{ $isOpen ? 'is-open' : 'is-closed' }}" data-attendance-session>
    @if($canControl)
        <div class="staff-attendance-session-actions">
            <form method="POST" action="{{ route('staff.attendance.open', $event) }}">
                @csrf
                <button type="submit" class="staff-btn staff-btn-success" data-attendance-open-btn {{ $isOpen ? 'disabled' : '' }}>Open Attendance</button>
            </form>
            <form
                method="POST"
                action="{{ route('staff.attendance.close', $event) }}"
                data-confirm="Scholars will immediately lose the ability to submit or change their attendance for {{ $event->title }}."
                data-confirm-title="Close Attendance?"
                data-confirm-yes="Close Attendance"
                data-confirm-no="Cancel"
                data-ajax-close="true"
                data-no-loading="true"
            >
                @csrf
                <button type="submit" class="staff-btn staff-btn-danger" data-attendance-close-btn {{ $isOpen ? '' : 'disabled' }}>Close Attendance</button>
            </form>
        </div>
    @endif
</section>
