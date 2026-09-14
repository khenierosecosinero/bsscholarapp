@php
    $sessions = $sessions ?? collect();
    $compact = $compact ?? false;
@endphp

<section class="card attendance-status-card {{ $compact ? 'is-compact' : '' }}" data-attendance-sessions @if($sessions->isEmpty()) hidden @endif>
    <div class="card-header">ATTENDANCE STATUS</div>
    <div class="attendance-status-list" data-attendance-session-list>
        @foreach($sessions as $session)
            @php $isOpen = ! empty($session['schedule_open']); @endphp
            <article class="attendance-status-item {{ $isOpen ? 'is-open' : 'is-closed' }}" data-event-id="{{ $session['event_id'] }}">
                <div class="attendance-status-top">
                    <strong>{{ $session['title'] }}</strong>
                    <span class="badge {{ $isOpen ? 'attendance-open' : 'attendance-closed' }}">{{ $session['schedule_status'] ?? $session['status'] }}</span>
                </div>
                <div class="muted">{{ $session['full_date'] }} · {{ $session['time'] }}</div>
                <p>{{ $session['schedule_message'] ?? $session['message'] }}</p>
                <a href="{{ route('user.events', ['event' => $session['event_id']]) }}" class="link small">View Event</a>
            </article>
        @endforeach
    </div>
</section>
