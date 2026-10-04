@extends('layouts.staff')

@section('page-content')

@include('partials.staff-report-period-filter')
<p class="staff-muted staff-report-scope">{{ $staff->scholarshipClubLabel() }}</p>

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
        <div class="staff-list-item"><div><strong>Required per scholar</strong><div class="staff-muted">{{ number_format($report['required'], 2) }} hours {{ $reportFilter['semester'] === 'all' ? 'this academic year (30 per semester)' : 'this semester' }}</div></div></div>
        <div class="staff-list-item"><div><strong>Approved</strong><div class="staff-muted">{{ number_format($report['overview']['approved_hours'], 2) }} hrs credited after verification</div></div></div>
        <div class="staff-list-item"><div><strong>Pending</strong><div class="staff-muted">{{ number_format($report['overview']['pending_hours'], 2) }} hrs waiting for Scholar Staff approval</div></div></div>
        <p class="staff-muted" style="margin:12px 0 0">Scholars who check in and complete an event receive that event’s posted service hours after you approve the attendance photo. Failed check-ins receive 0 hours.</p>
    </div>
</section>

@include('partials.staff-report-school-section', ['report' => $report, 'kind' => 'hours'])

@endsection
