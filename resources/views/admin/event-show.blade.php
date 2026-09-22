@extends('layouts.admin')

@section('page-content')

@php
    $backQuery = array_filter([
        'location' => (($locationKey ?? 'all') !== 'all') ? $locationKey : null,
        'program_type' => (($programType ?? 'all') !== 'all') ? $programType : null,
    ]);
@endphp

<div class="admin-event-attendance">
    <div class="staff-card admin-event-attendance-header">
        <div class="staff-card-header">
            <h2>{{ $event->title }}</h2>
            <span class="staff-badge {{ $event->scheduleBadgeClass() }}">{{ $event->scheduleLabel() }}</span>
        </div>
        <div class="admin-scholar-fields">
            <div class="admin-scholar-field">
                <span>Scholar Program</span>
                <strong>{{ $event->scholarshipProgram?->programLabel() ?? '—' }}</strong>
            </div>
            <div class="admin-scholar-field">
                <span>Program Type</span>
                <strong>{{ $event->scholarshipProgram?->programTypeLabel() ?? '—' }}</strong>
            </div>
            <div class="admin-scholar-field">
                <span>Schedule</span>
                <strong>{{ $event->starts_at?->format('M j, Y g:i A') ?? '—' }}</strong>
            </div>
            <div class="admin-scholar-field">
                <span>Event Service Hours</span>
                <strong>{{ number_format((float) $event->service_hours, 2) }} hrs</strong>
            </div>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Scholars Who Attended</h2>
            <span class="staff-muted">{{ $participants->count() }} {{ \Illuminate\Support\Str::plural('scholar', $participants->count()) }}</span>
        </div>
        <div class="staff-table-wrap">
            <table class="staff-table staff-stack-table">
                <thead>
                    <tr>
                        <th>Scholar</th>
                        <th>Attendance Status</th>
                        <th>Check-in</th>
                        <th>Service Hours Earned</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($participants as $participant)
                        @php
                            $statusClass = match($participant['status']) {
                                'approved', 'confirmed' => 'green',
                                'rejected', 'failed_to_check_in' => 'red',
                                default => 'orange',
                            };
                        @endphp
                        <tr>
                            <td data-label="Scholar">
                                <div class="staff-scholar-cell">
                                    <x-user-avatar :user="$participant['user']" class="staff-scholar-avatar" />
                                    <div class="staff-scholar-meta">
                                        <strong>{{ $participant['user']->full_name }}</strong>
                                        <small>{{ $participant['user']->scholar_id }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Attendance Status">
                                <span class="staff-badge {{ $statusClass }}">{{ $participant['status_label'] }}</span>
                            </td>
                            <td data-label="Check-in">{{ $participant['attendance']?->check_in?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td data-label="Service Hours Earned">{{ $participant['attendance']?->hoursLabel() ?? '0.00 hrs' }}</td>
                        </tr>
                    @empty
                        <tr class="staff-table-empty"><td colspan="4">No scholars are associated with this event yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <a href="{{ route('admin.events', $backQuery) }}" class="staff-card-link admin-scholar-back">&larr; Back to events list</a>
</div>

@endsection
