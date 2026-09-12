@php
    $isOpen = $event->isAttendanceOpen();
    $canControl = auth()->user()?->hasStaffPortalAccess();
@endphp

<section class="staff-attendance-session {{ $isOpen ? 'is-open' : 'is-closed' }}">
    <div class="staff-attendance-session-status">
        <span class="staff-badge {{ $event->attendanceStatusBadgeClass() }} staff-attendance-status-badge">{{ $event->attendanceStatusLabel() }}</span>
        <div>
            <strong>Attendance session</strong>
            <p class="staff-muted" style="margin:4px 0 0">
                @if($isOpen)
                    Opened {{ $event->attendanceOpenedAtLabel() ?? 'just now' }}. Scholars can mark attendance until you click Close Attendance. This session will not close automatically.
                @elseif($event->attendanceSessionClosed())
                    Closed {{ $event->attendanceClosedAtLabel() }}. Scholars can no longer submit or modify their attendance. You can still edit records below.
                @else
                    Closed. Scholars cannot mark attendance until you click Open Attendance. The event schedule does not open or close this session.
                @endif
            </p>
            @if($event->attendance_opened_at || $event->attendance_closed_at)
                <p class="staff-muted" style="margin:6px 0 0">
                    @if($event->attendance_opened_at)
                        Last opened: {{ $event->attendanceOpenedAtLabel() }}
                    @endif
                    @if($event->attendance_closed_at)
                        · Last closed: {{ $event->attendanceClosedAtLabel() }}
                    @endif
                </p>
            @endif
        </div>
    </div>

    @if($canControl)
        <div class="staff-attendance-session-actions">
            <form method="POST" action="{{ route('staff.attendance.open', $event) }}">
                @csrf
                <button type="submit" class="staff-btn staff-btn-success" {{ $isOpen ? 'disabled' : '' }}>Open Attendance</button>
            </form>
            <form
                method="POST"
                action="{{ route('staff.attendance.close', $event) }}"
                data-confirm="Scholars will immediately lose the ability to submit or change their attendance for {{ $event->title }}."
                data-confirm-title="Close Attendance?"
                data-confirm-yes="Close Attendance"
                data-confirm-no="Cancel"
            >
                @csrf
                <button type="submit" class="staff-btn staff-btn-danger" {{ $isOpen ? '' : 'disabled' }}>Close Attendance</button>
            </form>
        </div>
    @endif
</section>
