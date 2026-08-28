@extends('layouts.staff')

@section('page-content')

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">👥</div><div class="staff-stat-body"><h3>Registered</h3><div class="value">{{ $report['registered'] }}</div><div class="sub">Event sign-ups</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Participated</h3><div class="value">{{ $report['participated'] }}</div><div class="sub">Approved attendance</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending</h3><div class="value">{{ $report['pending'] }}</div><div class="sub">Checked in, not verified</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✕</div><div class="staff-stat-body"><h3>Failed to Check In</h3><div class="value">{{ $report['failed'] }}</div><div class="sub">0 service hours</div></div></div>
</section>

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header"><h2>Participation Overview</h2></div>
        @php
            $t = max(1, $report['participated'] + $report['pending'] + $report['failed'] + $report['confirmed']);
            $a = round($report['participated'] / $t * 100, 2);
            $p = round($report['pending'] / $t * 100, 2);
            $f = round($report['failed'] / $t * 100, 2);
        @endphp
        <div class="staff-donut-wrap">
            <div class="staff-donut" style="background: conic-gradient(#22c55e 0 {{ $a }}%, #f59e0b {{ $a }}% {{ $a + $p }}%, #991b1b {{ $a + $p }}% {{ $a + $p + $f }}%, #1890ff {{ $a + $p + $f }}% 100%)">
                <div class="staff-donut-inner">{{ $report['registered'] }}<br>Registered</div>
            </div>
            <ul class="staff-legend">
                <li><span class="staff-dot" style="background:#22c55e"></span> Participated: {{ $report['participated'] }}</li>
                <li><span class="staff-dot" style="background:#f59e0b"></span> Pending verification: {{ $report['pending'] }}</li>
                <li><span class="staff-dot" style="background:#991b1b"></span> Failed to check in: {{ $report['failed'] }}</li>
                <li><span class="staff-dot" style="background:#1890ff"></span> Confirmed (not yet attended): {{ $report['confirmed'] }}</li>
            </ul>
        </div>
    </div>
    <div class="staff-card">
        <div class="staff-card-header"><h2>Notes</h2></div>
        <p class="staff-muted" style="margin:0">A scholar who clicks Attend is registered. Service hours are only credited after check-in, check-out, a participation photo, and Scholar Staff approval. Scholars who register but never check in are marked Failed to Check In and receive 0 hours.</p>
    </div>
</section>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Participation by Event</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Registered</th>
                    <th>Checked In</th>
                    <th>Approved</th>
                    <th>Hours</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['events'] as $event)
                    <tr>
                        <td><a href="{{ route('staff.events.show', $event) }}">{{ $event->title }}</a></td>
                        <td>{{ $event->starts_at?->format('M j, Y') ?? '—' }}</td>
                        <td>{{ $event->registrations_count }}</td>
                        <td>{{ $event->checked_in_count }}</td>
                        <td>{{ $event->approved_count }}</td>
                        <td>{{ number_format((float) $event->service_hours, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No events found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
