@extends('layouts.staff')

@section('page-content')

@php
    $eventsByDay = collect();
    foreach ($monthEvents as $event) {
        foreach ($event->calendarDateKeys() as $dayKey) {
            $bucket = $eventsByDay->get($dayKey, collect());
            $eventsByDay->put($dayKey, $bucket->push($event));
        }
    }
    $gridStart = $monthDate->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::SUNDAY);
    $gridEnd = $monthDate->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
@endphp

<div class="staff-cal-toolbar">
    <a href="{{ route('staff.calendar', ['year' => now()->year, 'month' => now()->month]) }}" class="staff-btn">Today</a>
    <div class="staff-cal-month-nav">
        <a href="{{ route('staff.calendar', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" class="staff-cal-arrow" aria-label="Previous month">&#9664;</a>
        <strong>{{ $monthDate->format('F Y') }}</strong>
        <a href="{{ route('staff.calendar', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="staff-cal-arrow" aria-label="Next month">&#9654;</a>
    </div>
    <a href="{{ route('staff.events.create') }}" class="staff-btn staff-btn-primary">+ Add Event</a>
</div>

<section class="staff-grid-2 staff-cal-layout">
    <div class="staff-card staff-cal-card">
        <div class="staff-cal-grid">
            <div class="staff-cal-header">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
            </div>
            @while($gridStart->lte($gridEnd))
                <div class="staff-cal-row">
                    @for($i = 0; $i < 7; $i++)
                        @php
                            $inMonth = $gridStart->month === $monthDate->month;
                            $dayEvents = $eventsByDay->get($gridStart->toDateString(), collect());
                        @endphp
                        <div class="staff-cal-cell {{ !$inMonth ? 'other-month' : '' }} {{ $gridStart->isToday() ? 'today' : '' }}">
                            <span class="staff-cal-day-num">{{ $gridStart->day }}</span>
                            @foreach($dayEvents as $event)
                                <a href="{{ route('staff.events.show', $event) }}" class="staff-cal-event" title="{{ $event->title }} — {{ $event->starts_at->format('g:i A') }}">
                                    <span class="staff-cal-event-title">{{ $event->title }}</span>
                                    <span class="staff-cal-event-time">{{ $event->starts_at->format('g:i A') }}</span>
                                </a>
                            @endforeach
                        </div>
                        @php $gridStart->addDay(); @endphp
                    @endfor
                </div>
            @endwhile
        </div>
        <p class="staff-muted" style="margin:12px 0 0">Dates you set when creating an event appear on this calendar and on every matching scholar calendar the same day.</p>
    </div>

    <div>
        <div class="staff-card" style="margin-bottom:20px">
            <div class="staff-card-header">
                <h2>Upcoming Events</h2>
                <a href="{{ route('staff.events') }}" class="staff-card-link">View all</a>
            </div>
            @forelse($events as $event)
                <div class="staff-list-item">
                    <div class="staff-date-box">
                        <div class="month">{{ $event->starts_at->format('M') }}</div>
                        <div class="day">{{ $event->starts_at->format('d') }}</div>
                    </div>
                    <div class="staff-list-item-body">
                        <strong>{{ $event->title }}</strong>
                        <div class="staff-muted">{{ $event->starts_at->format('M j, Y · g:i A') }} · {{ $event->location ?? 'TBA' }}</div>
                    </div>
                </div>
            @empty
                <p class="staff-muted">No upcoming events scheduled.</p>
            @endforelse
        </div>

        <div class="staff-card">
            <div class="staff-card-header"><h2>Quick Actions</h2></div>
            <div class="staff-quick-actions">
                <a href="{{ route('staff.events.create') }}" class="staff-quick-btn blue">+ Add Event <span>›</span></a>
                <a href="{{ route('staff.attendance') }}" class="staff-quick-btn green">Activate Attendance <span>›</span></a>
            </div>
        </div>
    </div>
</section>

@endsection
