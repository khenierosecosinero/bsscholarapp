@extends('layouts.staff')

@section('page-content')

<div class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Event Details</h2>
            <a href="{{ route('staff.events') }}" class="staff-card-link">&larr; Back</a>
        </div>

        @if($event->image_url)
            <img src="{{ $event->image_url }}" alt="{{ $event->title }}" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;margin-bottom:16px">
        @endif

        <h3 style="margin:0 0 8px">{{ $event->title }}</h3>
        <span class="staff-badge {{ $event->statusBadgeClass() }}">{{ $event->statusLabel() }}</span>
        <span class="staff-badge {{ $event->attendanceStatusBadgeClass() }}">Attendance {{ $event->attendanceStatusLabel() }}</span>

        @if($event->description)
            <p class="staff-muted" style="margin:16px 0">{{ $event->description }}</p>
        @endif

        <div class="staff-list-item"><div><strong>Location</strong><div class="staff-muted">{{ $event->location }}</div></div></div>
        <div class="staff-list-item"><div><strong>Event Date</strong><div class="staff-muted">{{ $event->scheduleWhenLabel() }}</div></div></div>
        <div class="staff-list-item"><div><strong>Attendance</strong><div class="staff-muted">{{ $event->attendanceStatusLabel() }} · Opened at {{ $event->attendanceOpenedAtLabel() ?? 'Not yet opened' }} · Closed at {{ $event->attendanceSessionClosed() ? ($event->attendanceClosedAtLabel() ?? 'Not yet closed') : 'Not yet closed' }}</div></div></div>

        <form method="POST" action="{{ route('staff.events.schedule', $event) }}" class="staff-event-date-form">
            @csrf
            @method('PATCH')
            <div class="staff-form-grid staff-datetime-grid">
                <div class="staff-form-group">
                    <label for="starts_at">Event Start Date</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', $event->starts_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}" required>
                    @error('starts_at')<div class="staff-field-error">{{ $message }}</div>@enderror
                </div>
                <div class="staff-form-group">
                    <label for="ends_at">Event End Date</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', $event->ends_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}" required>
                    @error('ends_at')<div class="staff-field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="staff-btn staff-btn-primary">Save Event Dates</button>
        </form>
        <div class="staff-list-item"><div><strong>Service Hours</strong><div class="staff-muted">{{ number_format((float) $event->service_hours, 1) }} hrs</div></div></div>
        <div class="staff-list-item"><div><strong>Organizer</strong><div class="staff-muted">{{ $event->organizer ?? '—' }}</div></div></div>
        <div class="staff-list-item"><div><strong>Visibility</strong><div class="staff-muted">Published to all scholars in User / Events</div></div></div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header"><h2>Participation</h2></div>
        <div class="staff-stat-body">
            <div class="value">{{ $event->registrations_count ?? 0 }}</div>
            <div class="sub">Registered Scholars</div>
        </div>
        <p class="staff-muted" style="margin-top:16px">Open or close attendance from Staff &gt; Attendance. The session stays open until you close it and does not follow the event schedule. Scholars who registered but did not check in before the session closed are marked Failed to Check In and receive 0 service hours.</p>
        <a href="{{ route('staff.attendance', ['event' => $event->id]) }}" class="staff-btn staff-btn-primary" style="margin-top:12px">Manage Attendance</a>
    </div>
</div>

@if(isset($participants))
<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Attend/Register</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table staff-manage-table">
            <thead>
                <tr>
                    <th>Scholar Information</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Hours</th>
                    <th>Photo</th>
                    <th class="staff-col-status">Status</th>
                    <th class="staff-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($participants as $participant)
                    <tr>
                        <td data-label="Scholar Information">
                            <div class="staff-stack-value">
                                <div class="staff-scholar-cell">
                                    <x-user-avatar :user="$participant['user'] ?? null" class="staff-scholar-avatar" />
                                    <div class="staff-scholar-meta">
                                        <strong>{{ $participant['user']?->full_name ?? '—' }}</strong>
                                        <small>{{ $participant['user']?->scholar_id }}</small>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Check In"><div class="staff-stack-value">{{ $participant['attendance']?->check_in?->format('g:i A') ?? '—' }}</div></td>
                        <td data-label="Check Out"><div class="staff-stack-value">{{ $participant['attendance']?->check_out?->format('g:i A') ?? '—' }}</div></td>
                        <td data-label="Hours"><div class="staff-stack-value">{{ $participant['attendance']?->hoursLabel() ?? '0.00 hrs' }}</div></td>
                        <td data-label="Photo">
                            <div class="staff-stack-value">
                                @include('partials.staff-attendance-photo', [
                                    'attendance' => $participant['attendance'],
                                    'photoUrl' => $participant['attendance']?->hasPhoto() ? route('staff.attendances.photo', $participant['attendance']) : null,
                                ])
                            </div>
                        </td>
                        <td data-label="Status" class="staff-col-status">
                            <div class="staff-stack-value">
                                @php
                                    $statusClass = match($participant['status']) {
                                        'approved', 'confirmed' => 'green',
                                        'rejected', 'failed_to_check_in' => 'red',
                                        default => 'orange',
                                    };
                                @endphp
                                <span class="staff-badge {{ $statusClass }}">{{ $participant['status_label'] }}</span>
                            </div>
                        </td>
                        <td data-label="Actions" class="staff-col-actions">
                            <div class="staff-stack-value">
                                @if($participant['attendance'] && $participant['status'] !== 'failed_to_check_in')
                                    @include('partials.staff-attendance-actions', ['attendance' => $participant['attendance']])
                                @else
                                    <span class="staff-muted">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="7">No scholars have registered for this event yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@push('styles')
<style>.staff-muted{color:#6b7280;font-size:13px}</style>
@endpush
