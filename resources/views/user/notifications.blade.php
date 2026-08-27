@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/notifications-page.css') }}">
@endpush

@section('page-content')
<div class="page-notifications">

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
@endphp

<section class="notifications-page-grid">
    <div class="notifications-main">
        <div class="tabs-row">
            <div class="tabs">
                @foreach(['all' => 'All', 'unread' => 'Unread (' . $notifStats['unread'] . ')', 'important' => 'Important (' . $notifStats['important'] . ')'] as $key => $label)
                    <a href="{{ route('user.notifications', ['filter' => $key]) }}" class="tab {{ $activeFilter === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            <form method="POST" action="{{ route('user.notifications.read-all') }}">@csrf<button type="submit" class="link mark-read">&#10003; Mark all as read</button></form>
        </div>

        <div class="notification-list">
            @forelse($notifications as $notif)
                @php $meta = $categoryIcons[$notif->category] ?? ['icon' => '&#128276;', 'class' => 'blue']; @endphp
                <div class="notification-item {{ !$notif->is_read ? 'unread' : '' }}" data-id="{{ $notif->id }}">
                    <div class="notif-icon {{ $meta['class'] }}">{!! $meta['icon'] !!}</div>
                    <div class="notif-body">
                        <strong>{{ $notif->title }}</strong>
                        <p>{{ $notif->body }}</p>
                        <span class="badge info">{{ ucfirst(str_replace('_', ' ', $notif->category)) }}</span>
                        @if($notif->announcement_id)
                            <a href="{{ route('user.announcements.show', $notif->announcement_id) }}" class="link small notif-view-link">View Announcement</a>
                        @elseif($notif->category === 'event_reminder')
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
            @empty
                <p class="muted center">No notifications found.</p>
            @endforelse
        </div>
        <p class="table-footer muted center">Showing {{ $notifications->count() }} notification(s)</p>
    </div>

    <aside class="notifications-sidebar">
        <div class="card sidebar-page-card">
            <div class="card-header">NOTIFICATION SUMMARY</div>
            <div class="summary-rows">
                <div class="summary-row"><span class="summary-icon blue">&#128276;</span> Unread <strong>{{ $notifStats['unread'] }}</strong></div>
                <div class="summary-row"><span class="summary-icon green">&#10003;</span> Read <strong>{{ $notifStats['read'] }}</strong></div>
                <div class="summary-row"><span class="summary-icon red">&#9888;</span> Important <strong>{{ $notifStats['important'] }}</strong></div>
                <div class="summary-row"><span class="summary-icon purple">&#128202;</span> Total <strong>{{ $notifStats['total'] }}</strong></div>
            </div>
        </div>

        <div class="card sidebar-page-card">
            <div class="card-header">NOTIFICATION SETTINGS</div>
            <form method="POST" action="{{ route('user.notifications.settings') }}">
                @csrf
                <p class="muted small">Choose how you want to receive notifications.</p>
                <div class="toggle-list">
                    @foreach(['event_reminders' => 'Event Reminders', 'attendance_updates' => 'Attendance Updates', 'service_hours' => 'Service Hours', 'document_updates' => 'Document Updates', 'announcements' => 'Announcements'] as $key => $label)
                        <label class="toggle-item">
                            <span>{{ $label }}</span>
                            <input type="checkbox" name="{{ $key }}" value="1" {{ ($preferences[$key] ?? true) ? 'checked' : '' }}>
                            <span class="toggle"></span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn blue full notifications-save-btn">Save Settings</button>
            </form>
        </div>

        @include('partials.sidebar-help-card', [
            'helpText' => 'If you have questions about notifications, you can visit our Help Center.',
        ])
    </aside>
</section>

</div>

@endsection
