@extends('layouts.user')

@section('page-content')

<section class="announcements-page-grid">
    <div class="announcements-main">
        <div class="tabs-row">
            <div class="tabs">
                @foreach(['all' => 'All', 'unread' => 'Unread (' . $announcementStats['unread'] . ')'] as $key => $label)
                    <a href="{{ route('user.announcements', ['filter' => $key]) }}" class="tab {{ $activeFilter === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            @if($announcementStats['unread'] > 0)
                <form method="POST" action="{{ route('user.announcements.read-all') }}">
                    @csrf
                    <button type="submit" class="link mark-read">&#10003; Mark all as read</button>
                </form>
            @endif
        </div>

        <div class="announcement-list">
            @forelse($announcements as $ann)
                <a href="{{ route('user.announcements.show', $ann) }}" class="announcement-item {{ !$ann->is_read ? 'unread' : '' }}">
                    <div class="ann-icon blue">&#128226;</div>
                    <div class="announcement-body">
                        <div class="announcement-top">
                            <strong>{{ $ann->title }}</strong>
                            @if(!$ann->is_read)
                                <span class="status-dot blue" aria-label="Unread"></span>
                            @endif
                        </div>
                        <p>{{ Str::limit($ann->body, 140) }}</p>
                        <span class="announcement-date">{{ $ann->published_at?->format('M d, Y') ?? $ann->created_at->format('M d, Y') }}</span>
                    </div>
                    <span class="announcement-arrow" aria-hidden="true">&#9654;</span>
                </a>
            @empty
                <div class="card">
                    <p class="muted center">No announcements found.</p>
                </div>
            @endforelse
        </div>

        @if($announcements->isNotEmpty())
            <p class="table-footer muted center">Showing {{ $announcements->count() }} announcement(s)</p>
        @endif
    </div>

    <aside class="announcements-sidebar">
        <div class="card">
            <div class="card-header">ANNOUNCEMENT SUMMARY</div>
            <div class="summary-rows">
                <div class="summary-row"><span class="summary-icon blue">&#128226;</span> Total <strong>{{ $announcementStats['total'] }}</strong></div>
                <div class="summary-row"><span class="summary-icon orange">&#128276;</span> Unread <strong>{{ $announcementStats['unread'] }}</strong></div>
                <div class="summary-row"><span class="summary-icon green">&#10003;</span> Read <strong>{{ $announcementStats['read'] }}</strong></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">NEED HELP?</div>
            <p class="muted small">For questions about an announcement, contact support or check your notifications for related updates.</p>
            <a href="{{ route('user.notifications', ['filter' => 'all']) }}" class="btn outline full" style="margin-top:12px">View Notifications</a>
        </div>
    </aside>
</section>

@endsection
