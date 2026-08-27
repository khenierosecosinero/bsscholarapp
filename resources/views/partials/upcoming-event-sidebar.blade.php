<div class="card upcoming-event-sidebar-card">
    <div class="card-header">UPCOMING EVENT</div>

    @if($upcomingEvent)
        @php
            $statusClass = match ($upcomingEvent['registration_status']) {
                'confirmed' => 'confirmed',
                'pending' => 'pending',
                default => 'not-joined',
            };
            $statusLabel = ucfirst(str_replace('_', ' ', $upcomingEvent['registration_status']));
        @endphp

        <div class="upcoming-event-body">
            @if($upcomingEvent['image_url'])
                <img class="upcoming-event-thumb" src="{{ $upcomingEvent['image_url'] }}" alt="{{ $upcomingEvent['title'] }}">
            @else
                <div class="upcoming-event-thumb upcoming-event-thumb-placeholder" aria-hidden="true">&#9733;</div>
            @endif

            <div class="upcoming-event-info">
                <div class="upcoming-event-title">{{ $upcomingEvent['title'] }}</div>
                <div class="upcoming-event-meta">{{ $upcomingEvent['starts_at']->format('M d, Y') }} &bull; {{ $upcomingEvent['starts_at']->format('g:i A') }}</div>
                <div class="upcoming-event-meta">{{ $upcomingEvent['location'] }}</div>
                <span class="badge pill {{ $statusClass }} upcoming-event-status">{{ $statusLabel }}</span>
            </div>
        </div>

        <a href="{{ route('user.events', ['event' => $upcomingEvent['id']]) }}" class="upcoming-event-link">View Details</a>
    @else
        <p class="muted upcoming-event-empty">No upcoming events scheduled.</p>
    @endif
</div>
