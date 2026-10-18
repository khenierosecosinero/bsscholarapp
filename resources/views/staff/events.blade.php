@extends('layouts.staff')

@section('page-content')

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">📅</div><div class="staff-stat-body"><h3>Total Events</h3><div class="value">{{ $stats['total'] }}</div><div class="sub">All time</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">📆</div><div class="staff-stat-body"><h3>Upcoming Events</h3><div class="value">{{ $stats['upcoming'] }}</div><div class="sub">Scheduled ahead</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon purple">▶</div><div class="staff-stat-body"><h3>Ongoing Events</h3><div class="value">{{ $stats['ongoing'] }}</div><div class="sub">Currently active</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Approval</h3><div class="value">{{ $stats['pending'] }}</div><div class="sub">Awaiting review</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✓</div><div class="staff-stat-body"><h3>Completed Events</h3><div class="value">{{ $stats['completed'] }}</div><div class="sub">All time</div></div></div>
</section>

<form method="GET" class="staff-filter-bar">
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search events by title or keyword...">
    </div>
    <select name="status" class="staff-select" onchange="this.form.submit()">
        <option value="all" @selected($statusFilter === 'all')>All Status</option>
        <option value="confirmed" @selected($statusFilter === 'confirmed')>Confirmed</option>
        <option value="upcoming" @selected($statusFilter === 'upcoming')>Upcoming</option>
        <option value="pending" @selected($statusFilter === 'pending')>Pending</option>
        <option value="ongoing" @selected($statusFilter === 'ongoing')>Ongoing</option>
        <option value="cancelled" @selected($statusFilter === 'cancelled')>Cancelled</option>
        <option value="completed" @selected($statusFilter === 'completed')>Completed</option>
    </select>
    <button type="submit" class="staff-btn">Search</button>
    <a href="{{ route('staff.events.create') }}" class="staff-btn staff-btn-primary">+ Add Event</a>
</form>

<div class="staff-card">
    <div class="staff-table-wrap staff-data-table-wrap">
        <table class="staff-table staff-manage-table staff-data-table staff-events-table">
            <thead>
                <tr>
                    <th>Event Information</th>
                    <th>Date &amp; Time</th>
                    <th>Service Hours</th>
                    <th class="staff-col-status">Status</th>
                    <th class="staff-col-status">Attendance</th>
                    <th class="staff-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td data-label="Event Information">
                            <div class="staff-event-cell">
                                @if($event->image_url)
                                    <img src="{{ $event->image_url }}" alt="" class="staff-event-thumb">
                                @endif
                                <div>
                                    <strong>{{ $event->title }}</strong>
                                    @if($event->location)
                                        <div class="staff-muted">📍 {{ $event->location }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td data-label="Date &amp; Time">
                            {{ $event->starts_at?->format('M j, Y') }}
                            <div class="staff-muted">{{ $event->starts_at?->format('g:i A') }}@if($event->ends_at) – {{ $event->ends_at->format('g:i A') }}@endif</div>
                        </td>
                        <td data-label="Service Hours">{{ number_format((float) $event->service_hours, 1) }} hrs</td>
                        <td data-label="Status" class="staff-col-status"><span class="staff-badge {{ $event->statusBadgeClass() }}">{{ $event->statusLabel() }}</span></td>
                        <td data-label="Attendance" class="staff-col-status"><span class="staff-badge {{ $event->attendanceStatusBadgeClass() }}">{{ $event->attendanceStatusLabel() }}</span></td>
                        <td data-label="Actions" class="staff-col-actions">
                            @php
                                $statusSelectValue = $event->staffStatusSelectValue();
                                $canEditStatus = $event->canEditStaffStatus();
                            @endphp
                            <div class="staff-event-actions staff-event-actions-stack">
                                <a href="{{ route('staff.events.show', $event) }}" class="staff-btn staff-btn-sm staff-btn-view" title="View event details">View</a>

                                <form
                                    method="POST"
                                    action="{{ route('staff.events.status', $event) }}"
                                    class="staff-event-status-form"
                                    data-event-status-form
                                    data-no-loading="true"
                                    data-confirm="This event will be marked as Cancelled. Scholars will no longer see it as an active event."
                                    data-confirm-title="Cancel this event?"
                                    data-confirm-name="{{ $event->title }}"
                                    data-confirm-yes="Cancel Event"
                                    data-confirm-no="Keep Event"
                                    data-confirm-variant="danger"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <label class="staff-sr-only" for="event-status-{{ $event->id }}">Event status</label>
                                    <select
                                        id="event-status-{{ $event->id }}"
                                        name="status"
                                        class="staff-select staff-event-status-select"
                                        data-event-status-select
                                        data-previous="{{ $statusSelectValue }}"
                                        title="Change event status"
                                        @disabled(! $canEditStatus)
                                    >
                                        @if($event->status === \App\Models\Event::STATUS_COMPLETED)
                                            <option value="completed" selected>Completed</option>
                                        @else
                                            <option value="pending" @selected($statusSelectValue === 'pending')>Pending</option>
                                            <option value="confirmed" @selected($statusSelectValue === 'confirmed')>Confirmed</option>
                                            <option value="cancelled" @selected($statusSelectValue === 'cancelled')>Cancelled</option>
                                        @endif
                                    </select>
                                </form>

                                <div class="staff-action-menu" data-staff-action-menu>
                                    <button
                                        type="button"
                                        class="staff-btn staff-btn-sm staff-btn-more"
                                        data-staff-action-menu-toggle
                                        aria-haspopup="true"
                                        aria-expanded="false"
                                        title="More actions"
                                        aria-label="More actions for {{ $event->title }}"
                                    >More</button>
                                    <div class="staff-action-menu-dropdown" data-staff-action-menu-panel hidden>
                                        @if($event->canBeRemoved())
                                            <form
                                                method="POST"
                                                action="{{ route('staff.events.destroy', $event) }}"
                                                data-no-loading="true"
                                                data-confirm="This permanently removes the cancelled event and cannot be undone."
                                                data-confirm-title="Remove this event?"
                                                data-confirm-name="{{ $event->title }}"
                                                data-confirm-yes="Remove Event"
                                                data-confirm-no="Keep Event"
                                                data-confirm-variant="danger"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="staff-action-menu-item staff-action-menu-item-danger">Remove Event</button>
                                            </form>
                                        @else
                                            <button type="button" class="staff-action-menu-item is-disabled" disabled title="Only cancelled events can be removed">Remove Event</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="6">No events found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $events->links() }}</div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-event-status-select]').forEach(function (select) {
        var form = select.closest('[data-event-status-form]');
        if (!form || select.disabled) return;

        select.addEventListener('change', function () {
            var previous = select.getAttribute('data-previous') || '';
            if (select.value === previous) return;

            if (select.value === 'cancelled') {
                form.dataset.confirmed = '';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }

                var modal = document.getElementById('staff-confirm-modal');
                if (!modal) return;

                var resetIfDismissed = function () {
                    if (!modal.classList.contains('is-open') && form.dataset.confirmed !== 'true') {
                        select.value = previous;
                        modal.removeEventListener('transitionend', resetIfDismissed);
                    }
                };

                setTimeout(function () {
                    if (!modal.classList.contains('is-open') && form.dataset.confirmed !== 'true') {
                        select.value = previous;
                        return;
                    }
                    var observer = new MutationObserver(function () {
                        if (!modal.classList.contains('is-open')) {
                            if (form.dataset.confirmed !== 'true') {
                                select.value = previous;
                            }
                            observer.disconnect();
                        }
                    });
                    observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
                }, 0);
                return;
            }

            form.dataset.confirmed = 'true';
            form.submit();
        });
    });

    var menus = document.querySelectorAll('[data-staff-action-menu]');
    if (!menus.length) return;

    function closeAll() {
        menus.forEach(function (menu) {
            var toggle = menu.querySelector('[data-staff-action-menu-toggle]');
            var panel = menu.querySelector('[data-staff-action-menu-panel]');
            if (panel) panel.hidden = true;
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            menu.classList.remove('is-open');
        });
    }

    menus.forEach(function (menu) {
        var toggle = menu.querySelector('[data-staff-action-menu-toggle]');
        var panel = menu.querySelector('[data-staff-action-menu-panel]');
        if (!toggle || !panel) return;

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var willOpen = panel.hidden;
            closeAll();
            if (willOpen) {
                panel.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
                menu.classList.add('is-open');
            }
        });
    });

    document.addEventListener('click', closeAll);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAll();
    });
});
</script>
@endpush
