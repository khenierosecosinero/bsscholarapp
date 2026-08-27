@php
    $calRoute = $calRoute ?? 'user.profile';
    $monthDate = $sidebarMonthDate ?? now()->startOfMonth();
    $prevMonth = $sidebarPrevMonth ?? $monthDate->copy()->subMonth();
    $nextMonth = $sidebarNextMonth ?? $monthDate->copy()->addMonth();
    $events = collect($sidebarCalendarEvents ?? []);
    $eventDays = $events
        ->flatMap(fn ($event) => $event['calendar_days'] ?? [$event['starts_at']->toDateString()])
        ->filter(fn ($date) => \Carbon\Carbon::parse($date)->isSameMonth($monthDate))
        ->map(fn ($date) => (int) \Carbon\Carbon::parse($date)->day)
        ->unique()
        ->all();
    $calStart = $monthDate->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::SUNDAY);
    $calEnd = $monthDate->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
@endphp

<div class="card dashboard-sidebar-calendar">
    <div class="sidebar-cal-nav">
        <a href="{{ route($calRoute, ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" class="sidebar-cal-arrow" aria-label="Previous month">&#9664;</a>
        <span class="sidebar-cal-label">{{ $monthDate->format('F Y') }}</span>
        <a href="{{ route($calRoute, ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="sidebar-cal-arrow" aria-label="Next month">&#9654;</a>
    </div>

    <div class="mini-calendar sidebar-mini-calendar">
        <div class="cal-grid">
            <div class="cal-row cal-weekdays">
                <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
            </div>
            @while($calStart->lte($calEnd))
                <div class="cal-row">
                    @for($i = 0; $i < 7; $i++)
                        @php $inMonth = $calStart->month === $monthDate->month; @endphp
                        <div class="cal-day {{ !$inMonth ? 'outside' : '' }} {{ $inMonth && in_array($calStart->day, $eventDays, true) ? 'has-dot' : '' }} {{ $calStart->isToday() ? 'today' : '' }}">
                            {{ $inMonth ? $calStart->day : $calStart->day }}
                        </div>
                        @php $calStart->addDay(); @endphp
                    @endfor
                </div>
            @endwhile
        </div>
    </div>

    <a href="{{ route('user.calendar', ['year' => now()->year, 'month' => now()->month]) }}" class="go-to-today">Go to Today</a>
</div>
