@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard-events.css') }}">
@endpush

@section('page-content')

@php $accountPending = auth()->user()->isPendingApproval(); @endphp

@if($accountPending)
    <div class="account-pending-banner">
        Your account is awaiting approval from Scholar Staff. Some features are temporarily unavailable.
    </div>
@endif

<section class="stats grid">
    <div class="stat card">
        <div class="stat-icon green">&#9201;</div>
        <div class="stat-body">
            <div class="stat-title">Service Hours</div>
            <div class="stat-value">{{ $stats['service_hours']['value'] }}</div>
            <div class="stat-muted">Completed / Required</div>
        </div>
    </div>
    <div class="stat card">
        <div class="stat-icon blue">&#128197;</div>
        <div class="stat-body">
            <div class="stat-title">Upcoming Events</div>
            <div class="stat-value green-text">{{ $stats['upcoming_events'] }}</div>
            <div class="stat-muted">This Week</div>
        </div>
    </div>
    <div class="stat card">
        <div class="stat-icon orange">&#128336;</div>
        <div class="stat-body">
            <div class="stat-title">Pending Attendances</div>
            <div class="stat-value orange-text">{{ $stats['pending_attendances'] }}</div>
            <div class="stat-muted">For Verification</div>
        </div>
    </div>
    <div class="stat card">
        <div class="stat-icon purple">&#128276;</div>
        <div class="stat-body">
            <div class="stat-title">Notifications</div>
            <div class="stat-value purple-text">{{ $stats['notifications'] }}</div>
            <div class="stat-muted">Unread</div>
        </div>
    </div>
</section>

<section class="content-grid">
    <div class="left-col">
        @if(!$accountPending)
            @include('partials.user-attendance-status', ['sessions' => $attendanceSessions ?? collect()])
        @endif
        <div class="card events dashboard-upcoming-events">
            <div class="card-header">UPCOMING EVENTS @if(!$accountPending)<a class="view-all" href="{{ route('user.events') }}">View All</a>@endif</div>
            <div class="event-list">
                @forelse($events as $ev)
                    <div class="event-item">
                        <div class="event-date"><span class="month">{{ $ev['month'] }}</span><span class="day">{{ $ev['day'] }}</span><span class="dow">{{ $ev['dow'] }}</span></div>
                        @if($ev['image_url'])
                            <img class="event-thumb" src="{{ $ev['image_url'] }}" alt="">
                        @else
                            <span class="event-thumb event-thumb-placeholder" aria-hidden="true"></span>
                        @endif
                        <div class="event-details">
                            <div class="event-title">{{ $ev['title'] }}</div>
                            <div class="event-meta">{{ $ev['time'] }} · {{ $ev['location'] }}</div>
                            <div class="event-footer">
                                <span class="badge {{ $ev['status_class'] }}">{{ $ev['status_label'] }}</span>
                                <span class="badge {{ !empty($ev['attendance_open']) ? 'attendance-open' : 'attendance-closed' }}">{{ $ev['attendance_status'] ?? 'CLOSED' }}</span>
                                <span class="hours">Service Hours: {{ $ev['hours'] }}</span>
                            </div>
                        </div>
                        <div class="event-action">
                            @if(!$accountPending)
                                <a href="{{ route('user.events', ['event' => $ev['id']]) }}" class="event-view-btn">View Details</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="muted center">No upcoming events.</p>
                @endforelse
            </div>
        </div>

        <div class="card pending">
            <div class="card-header">PENDING ATTENDANCES</div>
            @if($pendingAttendances->isEmpty())
                <p class="muted">No pending attendances.</p>
            @else
                <table class="table">
                    <thead><tr><th>Event</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach($pendingAttendances as $att)
                            <tr>
                                <td><div class="table-event">@if($att->event?->image_url)<img src="{{ $att->event->image_url }}" alt="">@endif {{ $att->event?->title }}</div></td>
                                <td>{{ $att->event?->starts_at?->format('M d, Y') }}</td>
                                <td>{{ $att->check_in?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $att->check_out?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $att->hoursLabel() }}</td>
                                <td><span class="badge pending">Pending</span></td>
                                <td>@if(!$accountPending)<a href="{{ route('user.events', ['event' => $att->event_id]) }}" class="btn outline small">View Details</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card documents dashboard-documents-card">
            <div class="card-header">DOCUMENTS @if(!$accountPending)<a class="view-all" href="{{ route('user.documents') }}">View All</a>@endif</div>
            <div class="dashboard-doc-grid">
                @forelse($dashboardDocuments as $doc)
                    @php
                        $statusClass = match ($doc->status) {
                            'approved', 'submitted' => 'verified',
                            'pending' => 'pending',
                            'rejected' => 'rejected',
                            default => 'not-submitted',
                        };
                        $statusLabel = match ($doc->status) {
                            'approved' => 'Approved',
                            'submitted' => 'Verified',
                            'pending' => 'Pending',
                            'rejected' => 'Rejected',
                            default => 'Not Submitted',
                        };
                    @endphp
                    <div class="dashboard-doc-card">
                        <div class="dashboard-doc-top">
                            @include('partials.document-icon', ['slug' => $doc->documentType->slug, 'size' => 'md'])
                            <div class="dashboard-doc-info">
                                <div class="dashboard-doc-title">{{ $doc->documentType->name }}</div>
                                @if($doc->uploaded_at)
                                    <div class="dashboard-doc-date">Uploaded on {{ $doc->uploaded_at->format('M d, Y') }}</div>
                                @endif
                            </div>
                        </div>
                        <span class="dashboard-doc-status {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                @empty
        <p class="muted">No required documents have been posted yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <aside class="right-col">
        <div class="card activities">
            <div class="card-header">RECENT ACTIVITIES</div>
            <ul class="activity-list">
                @forelse($activities as $act)
                    <li>
                        <div class="activity-icon green">&#10003;</div>
                        <div><div class="text">{{ $act->description }}</div><div class="time">{{ $act->created_at->format('M d, Y - g:i A') }}</div></div>
                    </li>
                @empty
                    <li><p class="muted">No recent activities.</p></li>
                @endforelse
            </ul>
        </div>

        <div class="card announcements">
            <div class="card-header">
                ANNOUNCEMENTS
                <a class="view-all" href="{{ route('user.announcements') }}">View All</a>
            </div>
            <ul class="ann-list">
                @forelse($announcements as $ann)
                    <li class="{{ !$ann->is_read ? 'unread' : '' }}">
                        <a href="{{ route('user.announcements.show', $ann) }}" class="ann-link">
                            <div class="ann-icon blue">&#128226;</div>
                            <div class="ann-content">
                                <div class="ann-title-row">
                                    <strong>{{ $ann->title }}</strong>
                                    @if(!$ann->is_read)
                                        <span class="status-dot blue" aria-label="Unread"></span>
                                    @endif
                                </div>
                                <div class="muted ann-excerpt">{{ Str::limit($ann->body, 72) }}</div>
                                <div class="ann-date">{{ $ann->published_at?->format('M d, Y') ?? $ann->created_at->format('M d, Y') }}</div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li><p class="muted">No announcements.</p></li>
                @endforelse
            </ul>
            @if(($announcementStats['unread'] ?? 0) > 0)
                <a href="{{ route('user.announcements', ['filter' => 'unread']) }}" class="link center small">
                    {{ $announcementStats['unread'] }} unread announcement{{ $announcementStats['unread'] === 1 ? '' : 's' }}
                </a>
            @endif
        </div>

        <div class="card calendar">
            <div class="card-header">CALENDAR OF ACTIVITIES</div>
            <div class="mini-calendar">
                <div class="cal-header">{{ now()->format('F Y') }}</div>
                <div class="cal-grid">
                    <div class="cal-row cal-weekdays"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span></div>
                    @php
                        $calStart = now()->startOfMonth()->startOfWeek(\Carbon\Carbon::SUNDAY);
                        $calEnd = now()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
                        $eventDays = collect($calendarEvents)
                            ->flatMap(fn ($e) => $e['calendar_days'] ?? [$e['starts_at']->toDateString()])
                            ->filter(fn ($date) => \Carbon\Carbon::parse($date)->isSameMonth(now()))
                            ->map(fn ($date) => (int) \Carbon\Carbon::parse($date)->day)
                            ->unique()
                            ->all();
                        $currentMonth = now()->month;
                    @endphp
                    @while($calStart->lte($calEnd))
                        <div class="cal-row">
                            @for($i = 0; $i < 7; $i++)
                                @php $inMonth = $calStart->month === $currentMonth; @endphp
                                <div class="cal-day {{ !$inMonth ? 'empty' : '' }} {{ $inMonth && in_array($calStart->day, $eventDays) ? 'has-dot' : '' }} {{ $calStart->isToday() ? 'today' : '' }}">{{ $inMonth ? $calStart->day : '' }}</div>
                                @php $calStart->addDay(); @endphp
                            @endfor
                        </div>
                    @endwhile
                </div>
            </div>
        </div>
    </aside>
</section>

@endsection
