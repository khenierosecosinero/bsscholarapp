@php
    $categoryIcons = [
        'event_reminder' => ['icon' => '&#128197;', 'class' => 'blue'],
        'attendance' => ['icon' => '&#128336;', 'class' => 'orange'],
        'service_hours' => ['icon' => '&#10003;', 'class' => 'green'],
        'documents' => ['icon' => '&#128196;', 'class' => 'purple'],
        'reminder' => ['icon' => '&#9888;', 'class' => 'red'],
        'announcement' => ['icon' => '&#128226;', 'class' => 'blue'],
        'system' => ['icon' => '&#9993;', 'class' => 'green'],
    ];
    $meta = $categoryIcons[$notif->category] ?? ['icon' => '&#128276;', 'class' => 'blue'];
@endphp
<div class="notification-item {{ !$notif->is_read ? 'unread' : '' }}" data-id="{{ $notif->id }}" @if(!empty($isExtra)) data-notification-extra hidden @endif>
    <div class="notif-icon {{ $meta['class'] }}">{!! $meta['icon'] !!}</div>
    <div class="notif-body">
        <strong>{{ $notif->title }}</strong>
        <p>{{ $notif->body }}</p>
        <span class="badge info">{{ ucfirst(str_replace('_', ' ', $notif->category)) }}</span>
        @if($notif->announcement_id)
            <a href="{{ route('user.announcements.show', $notif->announcement_id) }}" class="link small notif-view-link">View Announcement</a>
        @elseif($notif->event_id)
            <a href="{{ route('user.events', ['event' => $notif->event_id]) }}" class="link small notif-view-link">View Event</a>
        @elseif($notif->category === 'event_reminder' || $notif->category === 'attendance')
            <a href="{{ route('user.events') }}" class="link small notif-view-link">View Events</a>
        @endif
    </div>
    <div class="notif-meta">
        <span class="notif-time">{{ $notif->created_at->diffForHumans() }}</span>
        <span class="status-dot {{ $notif->is_read ? 'green' : 'blue' }}"></span>
        @if(!$notif->is_read)
            <form method="POST" action="{{ route('user.notifications.read', $notif) }}">@csrf<button type="submit" class="link small">Mark read</button></form>
        @endif
    </div>
</div>
