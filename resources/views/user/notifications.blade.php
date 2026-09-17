@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/notifications-page.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/notifications-toggle.js') }}" defer></script>
@endpush

@section('page-content')
<div class="page-notifications">

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

        <div class="notification-list" data-notification-list>
            @forelse($notifications as $notif)
                @include('partials.user-notification-item', ['notif' => $notif])
            @empty
                <p class="muted center">No notifications found.</p>
            @endforelse
            @foreach($extraNotifications ?? [] as $notif)
                @include('partials.user-notification-item', ['notif' => $notif, 'isExtra' => true])
            @endforeach
        </div>
        @if(!empty($hasMoreNotifications))
            <div class="notifications-see-more-wrap">
                <button
                    type="button"
                    class="btn outline full notifications-see-more"
                    id="notifications-toggle"
                    data-preview-count="{{ $notificationPreviewCount }}"
                    data-total="{{ $filteredTotal }}"
                    data-more-url="{{ route('user.notifications.more', ['filter' => $activeFilter, 'offset' => $notificationPreviewCount]) }}"
                >See More Notifications</button>
            </div>
        @endif
        <p class="table-footer muted center" id="notifications-footer">Showing {{ $notifications->count() }} of {{ $filteredTotal }} notification(s)</p>
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

<script>
(function () {
    var button = document.getElementById('notifications-toggle');
    var footer = document.getElementById('notifications-footer');
    var list = document.querySelector('[data-notification-list]');
    if (!button) return;

    var expanded = false;
    var loaded = false;
    var previewCount = parseInt(button.getAttribute('data-preview-count'), 10) || 5;
    var total = parseInt(button.getAttribute('data-total'), 10) || previewCount;
    var moreUrl = button.getAttribute('data-more-url');

    function extras() {
        return document.querySelectorAll('[data-notification-extra]');
    }

    function setExpanded(isExpanded) {
        expanded = isExpanded;
        extras().forEach(function (item) {
            item.hidden = !isExpanded;
        });
        button.textContent = isExpanded ? 'Show Less Notifications' : 'See More Notifications';
        if (footer) {
            footer.textContent = 'Showing ' + (isExpanded ? list.querySelectorAll('.notification-item').length : previewCount) + ' of ' + total + ' notification(s)';
        }
    }

    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (expanded) {
            setExpanded(false);
            return;
        }

        if (loaded || extras().length) {
            loaded = true;
            setExpanded(true);
            return;
        }

        if (!moreUrl) return;

        button.disabled = true;
        fetch(moreUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) throw new Error('Unable to load more notifications.');
            return response.json();
        }).then(function (data) {
            if (data.html && list) {
                list.insertAdjacentHTML('beforeend', data.html);
            }
            if (data.total) total = data.total;
            loaded = true;
            setExpanded(true);
        }).catch(function () {
            button.textContent = 'See More Notifications';
        }).finally(function () {
            button.disabled = false;
        });
    });
})();
</script>

@endsection
