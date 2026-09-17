@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendance-photo.css') }}">
@endpush

@section('page-content')

<div class="calendar-toolbar">
    <div class="cal-nav">
        <a href="{{ route('user.calendar', ['year' => now()->year, 'month' => now()->month]) }}" class="btn outline small cal-today-btn">Today</a>
        <div class="cal-month-nav">
            <a href="{{ route('user.calendar', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" class="cal-arrow" aria-label="Previous month">&#9664;</a>
            <span class="cal-month-label">{{ $monthDate->format('F Y') }}</span>
            <a href="{{ route('user.calendar', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="cal-arrow" aria-label="Next month">&#9654;</a>
        </div>
    </div>
</div>

<section class="calendar-page-grid">
    <div class="calendar-main">
        <div class="card">
            <div class="full-calendar">
                <div class="fc-header"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                <div class="fc-body">
                    @php
                        $start = $monthDate->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::SUNDAY);
                        $end = $monthDate->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
                        $eventsByDay = collect();
                        foreach ($events as $ev) {
                            foreach ($ev['calendar_days'] ?? [$ev['starts_at']->toDateString()] as $dayKey) {
                                $bucket = $eventsByDay->get($dayKey, collect());
                                $eventsByDay->put($dayKey, $bucket->push($ev));
                            }
                        }
                        $colorMap = ['participated' => 'blue', 'confirmed' => 'green', 'pending' => 'orange', 'not_joined' => 'purple', 'failed_to_check_in' => 'red'];
                    @endphp
                    @while($start->lte($end))
                        <div class="fc-row">
                            @for($i = 0; $i < 7; $i++)
                                @php
                                    $inMonth = $start->month === $monthDate->month;
                                    $dayEvents = $eventsByDay->get($start->format('Y-m-d'), collect());
                                @endphp
                                <div class="fc-cell {{ !$inMonth ? 'other-month' : '' }} {{ $start->isToday() ? 'today' : '' }}">
                                    <span class="fc-day-num">{{ $start->day }}</span>
                    @foreach($dayEvents as $ev)
                                        <a href="{{ route('user.calendar', ['year' => $monthDate->year, 'month' => $monthDate->month, 'event' => $ev['id']]) }}" class="fc-event {{ $colorMap[$ev['calendar_status']] ?? 'blue' }}" title="{{ $ev['title'] }} — {{ $ev['status_label'] }}">{{ $ev['title'] }} | {{ $ev['starts_at']->format('g:i A') }} | {{ $ev['hours'] }} hrs</a>
                                    @endforeach
                                </div>
                                @php $start->addDay(); @endphp
                            @endfor
                        </div>
                    @endwhile
                </div>
            </div>
            <div class="cal-footer">
                <div class="cal-legend full">
                    <span><span class="legend-dot green"></span> Confirmed</span>
                    <span><span class="legend-dot blue"></span> Participated</span>
                    <span><span class="legend-dot orange"></span> Pending</span>
                    <span><span class="legend-dot red"></span> Failed to Check In</span>
                    <span><span class="legend-dot purple"></span> Not Joined</span>
                </div>
            </div>
        </div>

        @include('partials.schedule-summary', $scheduleStats ?? [])
    </div>

    <aside class="calendar-sidebar">
        <div class="calendar-sidebar-content">
            @if($selected)
                <div class="card event-detail-card">
                    @if($selected['image_url'])<img src="{{ $selected['image_url'] }}" alt="">@endif
                    @include('partials.event-status-badge', ['statusClass' => $selected['status_class'], 'statusLabel' => $selected['status_label']])
                    <span class="badge {{ !empty($selected['attendance_open']) ? 'attendance-open' : 'attendance-closed' }}">{{ $selected['attendance_status'] ?? 'CLOSED' }}</span>
                    <h3>{{ $selected['title'] }}</h3>
                    @if(!empty($selected['attendance_message']))
                        <p class="muted" style="margin:0 0 10px">{{ $selected['attendance_message'] }}</p>
                    @endif
                    <div class="info-row"><span class="info-icon">&#128197;</span> {{ $selected['starts_at']->format('M d, Y') }}</div>
                    <div class="info-row"><span class="info-icon">&#128336;</span> {{ $selected['time'] }}</div>
                    <div class="info-row"><span class="info-icon">&#128205;</span> {{ $selected['location'] }}</div>
                    <div class="info-row"><span class="info-icon">&#9201;</span> Service Hours: {{ $selected['hours'] }}</div>

                    @if($selected['has_participated'])
                        <div class="participation-record-box">
                            <strong>Participation Confirmed</strong>
                            <p>Your attendance for this event has been officially verified.</p>
                            @if($selected['attendance']?->hours_earned)
                                <p class="participation-hours">{{ $selected['attendance']->hoursLabel() }} credited.</p>
                            @endif
                        </div>
                    @elseif($selected['failed_to_check_in'])
                        <div class="participation-record-box failed">
                            <strong>Failed to Check In</strong>
                            <p>You registered for this event but did not check in. No service hours were credited.</p>
                        </div>
                    @elseif($selected['attendance']?->status === 'pending' && $selected['attendance']->check_out)
                        <div class="participation-record-box pending">
                            <strong>{{ $selected['attendance']->hasPhoto() ? 'Pending Verification' : 'Photo Required' }}</strong>
                            <p>{{ $selected['attendance']->hasPhoto()
                                ? 'Your attendance has been submitted and is awaiting official confirmation.'
                                : 'Attach a participation photo on the event page so Scholar Staff can verify your attendance.' }}</p>
                        </div>
                    @endif

                    @if($selected['failed_to_check_in'] || ($selected['attendance'] && ($selected['has_participated'] || $selected['attendance']->check_out)))
                        @php $att = $selected['attendance']; @endphp
                        <div class="card inner-card calendar-attendance-card">
                            <div class="card-header">Your Participation Record</div>
                            <div class="table-wrap">
                            <table class="table compact">
                                <thead><tr><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr></thead>
                                <tbody><tr>
                                    <td>{{ $att?->check_in?->format('g:i A') ?? '—' }}</td>
                                    <td>{{ $att?->check_out?->format('g:i A') ?? '—' }}</td>
                                    <td>{{ $att?->hoursLabel() ?? '0.00 hrs' }}</td>
                                    <td>{{ $selected['status_label'] }}</td>
                                </tr></tbody>
                            </table>
                            </div>
                            @if($att?->hasPhoto())
                                <div class="attendance-record-photo">
                                    <span>Participation photo</span>
                                    <a href="{{ route('user.attendances.photo', $att) }}" target="_blank" rel="noopener">View photo</a>
                                </div>
                            @endif
                            @if($att?->status === 'pending' && $att?->isReadyForVerification() && auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('user.attendances.approve', $att->id) }}" class="attendance-approve-form">
                                    @csrf
                                    <button type="submit" class="btn full">Confirm Participation</button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <a href="{{ route('user.events', ['event' => $selected['id']]) }}" class="btn full">View Event Details</a>
                </div>
            @endif
        </div>

        @include('partials.calendar-upcoming-events')
    </aside>
</section>

@endsection
