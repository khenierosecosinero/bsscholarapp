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
        <option value="completed" @selected($statusFilter === 'completed')>Completed</option>
    </select>
    <button type="submit" class="staff-btn">Search</button>
    <a href="{{ route('staff.events.create') }}" class="staff-btn staff-btn-primary">+ Add Event</a>
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Event Information</th>
                    <th>Date &amp; Time</th>
                    <th>Service Hours</th>
                    <th>Status</th>
                    <th>Attendance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    @php
                        $statusClass = match($event->status ?? 'upcoming') {
                            'confirmed', 'completed' => 'green',
                            'pending' => 'orange',
                            'ongoing' => 'blue',
                            default => 'gray',
                        };
                    @endphp
                    <tr>
                        <td>
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
                        <td>
                            {{ $event->starts_at?->format('M j, Y') }}
                            <div class="staff-muted">{{ $event->starts_at?->format('g:i A') }}@if($event->ends_at) – {{ $event->ends_at->format('g:i A') }}@endif</div>
                        </td>
                        <td>{{ number_format((float) $event->service_hours, 1) }} hrs</td>
                        <td><span class="staff-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $event->status ?? 'upcoming')) }}</span></td>
                        <td><span class="staff-badge {{ $event->attendanceStatusBadgeClass() }}">{{ $event->attendanceStatusLabel() }}</span></td>
                        <td>
                            <a href="{{ route('staff.events.show', $event) }}" class="staff-action-btn" title="View">👁</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No events found. <a href="{{ route('staff.events.create') }}">Create your first event</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $events->links() }}</div>
</div>

@endsection

@push('styles')
<style>
.staff-muted{color:#6b7280;font-size:13px}
.staff-event-cell{display:flex;align-items:center;gap:12px}
.staff-event-thumb{width:48px;height:48px;border-radius:8px;object-fit:cover;flex-shrink:0}
.staff-stat-icon.red{background:#fee2e2}
</style>
@endpush
