<div class="card calendar-upcoming-card">
    <div class="card-header">UPCOMING EVENTS</div>

    <div class="upcoming-mini-list">
        @forelse($upcomingEvents ?? [] as $ev)
            <a href="{{ route('user.calendar', ['year' => $ev['starts_at']->year, 'month' => $ev['starts_at']->month, 'event' => $ev['id']]) }}" class="upcoming-item">
                <div class="event-date sm">
                    <span class="month">{{ $ev['month'] }}</span>
                    <span class="day">{{ $ev['day'] }}</span>
                    <span class="dow">{{ $ev['dow'] }}</span>
                </div>
                <div class="upcoming-item-body">
                    <strong>{{ $ev['title'] }}</strong>
                    <span class="muted">{{ $ev['starts_at']->format('g:i A') }} &middot; {{ $ev['location'] }}</span>
                    @include('partials.event-status-badge', ['statusClass' => $ev['status_class'], 'statusLabel' => $ev['status_label'], 'small' => true])
                </div>
            </a>
        @empty
            <p class="muted calendar-upcoming-empty">No upcoming events.</p>
        @endforelse
    </div>

    <a href="{{ route('user.events') }}" class="calendar-upcoming-link">View All Events &rarr;</a>
</div>
