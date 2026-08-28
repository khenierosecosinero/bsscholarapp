@extends('layouts.staff')

@section('page-content')

@php $b = $report['breakdown']; @endphp

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved</h3><div class="value">{{ $b['approved'] }}</div><div class="sub">Verified check-ins</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending</h3><div class="value">{{ $b['pending'] }}</div><div class="sub">Awaiting review</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✕</div><div class="staff-stat-body"><h3>Rejected</h3><div class="value">{{ $b['rejected'] }}</div><div class="sub">Not verified</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">📅</div><div class="staff-stat-body"><h3>Failed to Check In</h3><div class="value">{{ $b['failedCheckIn'] }}</div><div class="sub">0 service hours</div></div></div>
</section>

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header"><h2>Attendance Overview</h2></div>
        @php
            $t = max(1, $b['approved'] + $b['pending'] + $b['rejected'] + $b['failedCheckIn']);
            $a = round($b['approved'] / $t * 100, 2);
            $p = round($b['pending'] / $t * 100, 2);
            $r = round($b['rejected'] / $t * 100, 2);
        @endphp
        <div class="staff-donut-wrap">
            <div class="staff-donut" style="background: conic-gradient(#22c55e 0 {{ $a }}%, #f59e0b {{ $a }}% {{ $a + $p }}%, #ef4444 {{ $a + $p }}% {{ $a + $p + $r }}%, #991b1b {{ $a + $p + $r }}% 100%)">
                <div class="staff-donut-inner">{{ $report['rate'] }}%<br>Check-in</div>
            </div>
            <ul class="staff-legend">
                <li><span class="staff-dot" style="background:#22c55e"></span> Approved: {{ $b['approved'] }}</li>
                <li><span class="staff-dot" style="background:#f59e0b"></span> Pending: {{ $b['pending'] }}</li>
                <li><span class="staff-dot" style="background:#ef4444"></span> Rejected: {{ $b['rejected'] }}</li>
                <li><span class="staff-dot" style="background:#991b1b"></span> Failed to Check In: {{ $b['failedCheckIn'] }}</li>
            </ul>
        </div>
    </div>
    <div class="staff-card">
        <div class="staff-card-header"><h2>How Hours Are Credited</h2></div>
        <div class="staff-list-item"><div><strong>Checked in and completed</strong><div class="staff-muted">Event service hours are calculated, then credited when you approve the photo.</div></div></div>
        <div class="staff-list-item"><div><strong>Failed to check in</strong><div class="staff-muted">Registered scholars who never check in receive 0 hours.</div></div></div>
        <div class="staff-list-item"><div><strong>Rejected</strong><div class="staff-muted">Unverified attendance receives 0 hours until a new photo is approved.</div></div></div>
    </div>
</section>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header">
        <h2>Latest Attendance Records</h2>
        <a href="{{ route('staff.attendance') }}" class="staff-card-link">Open Attendance</a>
    </div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Event</th>
                    <th>Check In / Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['records'] as $attendance)
                    <tr>
                        <td>{{ $attendance->user?->full_name ?? '—' }}</td>
                        <td>{{ $attendance->event?->title ?? '—' }}</td>
                        <td>
                            {{ $attendance->check_in?->format('g:i A') ?? '—' }}
                            <div class="staff-muted">{{ $attendance->check_out?->format('g:i A') ?? '—' }}</div>
                        </td>
                        <td>{{ $attendance->hoursLabel() }}</td>
                        <td>
                            @php
                                $statusClass = match($attendance->status) {
                                    'approved' => 'green',
                                    'rejected', 'failed_to_check_in' => 'red',
                                    default => 'orange',
                                };
                            @endphp
                            <span class="staff-badge {{ $statusClass }}">{{ $attendance->statusLabel() }}</span>
                        </td>
                        <td>@include('partials.staff-attendance-actions', ['attendance' => $attendance])</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No attendance records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
