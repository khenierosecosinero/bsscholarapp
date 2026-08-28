@extends('layouts.staff')

@section('page-content')

<p class="staff-muted" style="margin:0 0 16px">{{ $report['period']->semester }} · AY {{ $report['period']->year_start }}–{{ $report['period']->year_end }} · {{ $staff->locationLabel() }}</p>

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved Hours</h3><div class="value">{{ number_format($report['overview']['approved_hours'], 2) }}</div><div class="sub">Credited this location</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Hours</h3><div class="value">{{ number_format($report['overview']['pending_hours'], 2) }}</div><div class="sub">Awaiting verification</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✕</div><div class="staff-stat-body"><h3>Rejected Records</h3><div class="value">{{ $report['overview']['rejected_count'] }}</div><div class="sub">0 hours credited</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">📅</div><div class="staff-stat-body"><h3>Failed Check-Ins</h3><div class="value">{{ $report['overview']['failed_count'] }}</div><div class="sub">0 hours credited</div></div></div>
</section>

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header"><h2>Completion Status</h2></div>
        @php
            $totalScholars = max(1, $report['completed'] + $report['in_progress'] + $report['not_started']);
            $c = round($report['completed'] / $totalScholars * 100, 2);
            $i = round($report['in_progress'] / $totalScholars * 100, 2);
        @endphp
        <div class="staff-donut-wrap">
            <div class="staff-donut" style="background: conic-gradient(#22c55e 0 {{ $c }}%, #1890ff {{ $c }}% {{ $c + $i }}%, #9ca3af {{ $c + $i }}% 100%)">
                <div class="staff-donut-inner">{{ $report['completed'] + $report['in_progress'] + $report['not_started'] }}<br>Scholars</div>
            </div>
            <ul class="staff-legend">
                <li><span class="staff-dot" style="background:#22c55e"></span> Completed ({{ $report['required'] }}+ hrs): {{ $report['completed'] }}</li>
                <li><span class="staff-dot" style="background:#1890ff"></span> In Progress: {{ $report['in_progress'] }}</li>
                <li><span class="staff-dot" style="background:#9ca3af"></span> Not Started: {{ $report['not_started'] }}</li>
            </ul>
        </div>
    </div>
    <div class="staff-card">
        <div class="staff-card-header"><h2>Hours Summary</h2></div>
        <div class="staff-list-item"><div><strong>Required per scholar</strong><div class="staff-muted">{{ $report['required'] }} hours / semester</div></div></div>
        <div class="staff-list-item"><div><strong>Approved</strong><div class="staff-muted">{{ number_format($report['overview']['approved_hours'], 2) }} hrs credited after verification</div></div></div>
        <div class="staff-list-item"><div><strong>Pending</strong><div class="staff-muted">{{ number_format($report['overview']['pending_hours'], 2) }} hrs waiting for Scholar Staff approval</div></div></div>
        <p class="staff-muted" style="margin:12px 0 0">Scholars who check in and complete an event receive that event’s posted service hours after you approve the attendance photo. Failed check-ins receive 0 hours.</p>
    </div>
</section>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Service Hours by Scholar</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Location</th>
                    <th>Approved</th>
                    <th>Pending</th>
                    <th>Remaining</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td>
                            <a href="{{ route('staff.scholars.show', $row['scholar']) }}">{{ $row['scholar']->full_name }}</a>
                            <div class="staff-muted">{{ $row['scholar']->scholar_id }}</div>
                        </td>
                        <td>{{ $row['scholar']->locationLabel() }}</td>
                        <td>{{ number_format($row['approved'], 2) }}</td>
                        <td>{{ number_format($row['pending'], 2) }}</td>
                        <td>{{ number_format($row['remaining'], 2) }}</td>
                        <td>
                            <span class="staff-badge {{ $row['status'] === 'Completed' ? 'green' : ($row['status'] === 'In Progress' ? 'blue' : 'gray') }}">{{ $row['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No scholars found for this location.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Recent Attendance Records</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Event</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['recent'] as $attendance)
                    <tr>
                        <td>{{ $attendance->user?->full_name ?? '—' }}</td>
                        <td>{{ $attendance->event?->title ?? '—' }}</td>
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
                    <tr><td colspan="5">No attendance records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
