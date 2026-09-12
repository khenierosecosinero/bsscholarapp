@php
    $sessions = $sessions ?? collect();
    $compact = $compact ?? false;
@endphp

<section class="card attendance-status-card {{ $compact ? 'is-compact' : '' }}" data-attendance-sessions>
    <div class="card-header">ATTENDANCE STATUS</div>
    <div class="attendance-status-list" data-attendance-session-list>
        @forelse($sessions as $session)
            <article class="attendance-status-item {{ $session['is_open'] ? 'is-open' : 'is-closed' }}" data-event-id="{{ $session['event_id'] }}">
                <div class="attendance-status-top">
                    <strong>{{ $session['title'] }}</strong>
                    <span class="badge {{ $session['is_open'] ? 'attendance-open' : 'attendance-closed' }}">{{ $session['status'] }}</span>
                </div>
                <div class="muted">{{ $session['full_date'] }} · {{ $session['time'] }}</div>
                <p>{{ $session['message'] }}</p>
                <a href="{{ route('user.events', ['event' => $session['event_id']]) }}" class="link small">View Event</a>
            </article>
        @empty
            <p class="muted" data-attendance-empty>No attendance session has been opened yet. Scholar Staff will open attendance when scholars can mark it.</p>
        @endforelse
    </div>
</section>
