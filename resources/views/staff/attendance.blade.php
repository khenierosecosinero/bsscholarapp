@extends('layouts.staff')

@section('page-content')

@if($panel['selected'])
    @php $event = $panel['selected']; @endphp

    <div class="staff-card staff-event-selector-open" style="margin-bottom:20px">
        <div class="staff-card-header">
            <div>
                <h2>{{ $event->title }}</h2>
                <p class="staff-muted" style="margin:4px 0 0">
                    {{ $event->starts_at?->format('M j, Y') }}
                    · {{ $event->starts_at?->format('g:i A') }}{{ $event->ends_at ? ' – '.$event->ends_at->format('g:i A') : '' }}
                    · {{ $event->location }}
                </p>
            </div>
            <a href="{{ route('staff.attendance', array_filter(['search' => $search ?: null])) }}" class="staff-card-link">&larr; Back to events</a>
        </div>
        <div class="staff-doc-type-counts">
            <span class="staff-badge {{ $event->scheduleBadgeClass() }}">{{ $event->scheduleLabel() }}</span>
            <span class="staff-badge {{ $event->attendanceStatusBadgeClass() }}" data-attendance-status-badge>{{ $event->attendanceStatusLabel() }}</span>
            <span>Service Hours: {{ number_format((float) $event->service_hours, 2) }}</span>
            <span class="approved">{{ $panel['checkedIn']->count() }} checked in</span>
            <span class="rejected">{{ $panel['failed']->count() }} failed to check in</span>
        </div>
        @include('partials.staff-attendance-session', ['event' => $event])
    </div>

    <div class="staff-attendance-split">
        <section class="staff-card staff-attendance-group">
            <h4>Scholars Who Checked In <span>{{ $panel['checkedIn']->count() }}</span></h4>
            <p class="staff-muted" style="margin:0 8px 12px">Review each scholar’s check-in time, participation photo, status, and hours before approving service hours.</p>
            @forelse($panel['checkedIn'] as $row)
                @php $attendance = $row['attendance']; @endphp
                <article class="staff-attendance-record">
                    <div class="staff-scholar-cell">
                        <x-user-avatar :user="$row['user'] ?? null" class="staff-scholar-avatar" />
                        <div class="staff-scholar-meta">
                            <strong>{{ $row['user']?->full_name ?? '—' }}</strong>
                            <small>{{ $row['user']?->scholar_id }}</small>
                        </div>
                    </div>
                    <dl class="staff-attendance-facts">
                        <div>
                            <dt>Check in</dt>
                            <dd>{{ $attendance?->check_in?->format('g:i A') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Check out</dt>
                            <dd>{{ $attendance?->check_out?->format('g:i A') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Hours</dt>
                            <dd>{{ $attendance?->hoursLabel() ?? '0.00 hrs' }}</dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>
                                @php
                                    $statusClass = match($attendance?->status) {
                                        'approved' => 'green',
                                        'rejected', 'failed_to_check_in' => 'red',
                                        default => 'orange',
                                    };
                                @endphp
                                <span class="staff-badge {{ $statusClass }}">{{ $attendance?->statusLabel() ?? 'Pending' }}</span>
                            </dd>
                        </div>
                    </dl>
                    <div class="staff-attendance-record-proof">
                        <span class="staff-muted">Proof / photo</span>
                        @include('partials.staff-attendance-photo', [
                            'attendance' => $attendance,
                            'photoUrl' => $attendance?->hasPhoto() ? route('staff.attendances.photo', $attendance) : null,
                        ])
                    </div>
                    <div class="staff-attendance-record-actions">
                        @if($attendance)
                            @include('partials.staff-attendance-actions', ['attendance' => $attendance])
                        @endif
                    </div>
                </article>
            @empty
                <p class="staff-muted">No scholars have checked in for this event.</p>
            @endforelse
        </section>

        <section class="staff-card staff-attendance-group failed">
            <h4>Scholars Who Failed to Check In <span>{{ $panel['failed']->count() }}</span></h4>
            <p class="staff-muted" style="margin:0 8px 12px">Scholars who clicked Attend / Register but did not check in. They receive Failed to Check In status and 0 service hours.</p>
            @forelse($panel['failed'] as $row)
                <article class="staff-attendance-record">
                    <div class="staff-scholar-cell">
                        <x-user-avatar :user="$row['user'] ?? null" class="staff-scholar-avatar" />
                        <div class="staff-scholar-meta">
                            <strong>{{ $row['user']?->full_name ?? '—' }}</strong>
                            <small>{{ $row['user']?->scholar_id }}</small>
                        </div>
                    </div>
                    <dl class="staff-attendance-facts">
                        <div>
                            <dt>Check in</dt>
                            <dd>—</dd>
                        </div>
                        <div>
                            <dt>Hours</dt>
                            <dd>0.00 hrs</dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd><span class="staff-badge red">Failed to Check In</span></dd>
                        </div>
                    </dl>
                </article>
            @empty
                <p class="staff-muted">No failed check-ins for this event.</p>
            @endforelse
        </section>
    </div>
@else
    <p class="staff-muted" style="margin:0 0 12px">Select an event to open or close its attendance session. Attendance stays open until you click Close Attendance and does not follow the event schedule.</p>
    <form method="GET" class="staff-filter-bar">
        <div class="staff-search">
            <span>🔍</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search events by name, location, or scholar...">
        </div>
        <button type="submit" class="staff-btn">Search</button>
    </form>

    @if($panel['events']->isEmpty())
        <div class="staff-card">
            <p class="staff-muted" style="margin:0">No events found. Create an event first, then scholars can register and check in.</p>
        </div>
    @else
        <div class="staff-doc-type-grid">
            @foreach($panel['events'] as $event)
                <article class="staff-doc-type-card">
                    <a href="{{ route('staff.attendance', array_filter(['event' => $event->id, 'search' => $search ?: null])) }}" class="staff-doc-type-main">
                        @include('partials.document-icon', ['slug' => 'event', 'size' => 'lg'])
                        <div class="staff-doc-type-body">
                            <div class="staff-doc-type-title-row">
                                <h3>{{ $event->title }}</h3>
                                <span class="staff-attendance-card-badges">
                                    <span class="staff-badge {{ $event->scheduleBadgeClass() }}">{{ $event->scheduleLabel() }}</span>
                                    <span class="staff-badge {{ $event->attendanceStatusBadgeClass() }}">{{ $event->attendanceStatusLabel() }}</span>
                                </span>
                            </div>
                            <p>
                                {{ $event->starts_at?->format('M j, Y') }}
                                · {{ $event->starts_at?->format('g:i A') }}{{ $event->ends_at ? ' – '.$event->ends_at->format('g:i A') : '' }}
                            </p>
                            <p>{{ $event->location }}</p>
                            <div class="staff-doc-type-counts">
                                <span>Service Hours: {{ number_format((float) $event->service_hours, 2) }}</span>
                                <span class="approved">{{ $event->checked_in_count }} checked in</span>
                                <span class="rejected">{{ $event->failed_count }} failed to check in</span>
                            </div>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
    @endif
@endif

@endsection
