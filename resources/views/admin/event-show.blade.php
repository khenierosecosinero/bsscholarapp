@extends('layouts.admin')

@section('page-content')

@php
    $backQuery = array_filter([
        'region' => request('region') ?: null,
        'location' => request('location', $locationKey ?? 'all'),
        'club' => request('club') ?: null,
        'search' => request('search') ?: null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="admin-event-attendance">
    <div class="staff-card admin-event-attendance-header">
        <div class="staff-card-header">
            <h2>{{ $event->title }}</h2>
            <span class="staff-badge {{ $event->scheduleBadgeClass() }}">{{ $event->scheduleLabel() }}</span>
        </div>
        <div class="admin-scholar-fields">
            <div class="admin-scholar-field">
                <span>Scholarship Club</span>
                <strong>{{ $event->directoryClubLabel(request()->integer('club') ?: null) }}</strong>
            </div>
            <div class="admin-scholar-field">
                <span>Participants</span>
                <strong>{{ $event->verifiedParticipantCount() }}</strong>
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
                        <th>Check-In</th>
                        <th>Service Hours Earned</th>
                        <th>Attendance Photo</th>
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
                            <td data-label="Check-In">{{ $participant['attendance']?->check_in?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td data-label="Service Hours Earned">{{ $participant['attendance']?->hoursLabel() ?? '0.00 hrs' }}</td>
                            <td data-label="Attendance Photo">
                                @php
                                    $attendance = $participant['attendance'];
                                    $hasApprovedPhoto = $attendance?->hasApprovedPhoto();
                                    $photoUrl = $hasApprovedPhoto
                                        ? route('admin.events.attendances.photo', [$event, $attendance])
                                        : null;
                                @endphp
                                @if($photoUrl)
                                    <button
                                        type="button"
                                        class="admin-attendance-photo-trigger user-avatar-preview-trigger"
                                        data-avatar-preview="{{ $photoUrl }}"
                                        data-avatar-name="{{ $participant['user']->full_name }}"
                                        data-avatar-alt="Approved attendance photo of {{ $participant['user']->full_name }}"
                                        title="View approved attendance photo of {{ $participant['user']->full_name }}"
                                        aria-label="View approved attendance photo of {{ $participant['user']->full_name }}"
                                    >
                                        <img
                                            src="{{ $photoUrl }}"
                                            alt="Approved attendance photo of {{ $participant['user']->full_name }}"
                                            class="admin-attendance-photo"
                                        >
                                    </button>
                                @else
                                    <span class="staff-muted">No approved photo</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="staff-table-empty"><td colspan="5">No scholars are associated with this event yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <a href="{{ route('admin.events', $backQuery) }}" class="staff-card-link admin-scholar-back">&larr; Back to events list</a>
</div>

@endsection
