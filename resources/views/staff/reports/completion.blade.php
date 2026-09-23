@extends('layouts.staff')

@section('page-content')

@include('partials.staff-report-period-filter')

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Completed</h3><div class="value">{{ $report['completed'] }}</div><div class="sub">{{ $report['completed_pct'] }}% of scholars</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon blue">📈</div><div class="staff-stat-body"><h3>In Progress</h3><div class="value">{{ $report['in_progress'] }}</div><div class="sub">Hours started, not finished</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon gray">○</div><div class="staff-stat-body"><h3>Not Started</h3><div class="value">{{ $report['not_started'] }}</div><div class="sub">No approved hours yet</div></div></div>
</section>

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header"><h2>Completion Overview</h2></div>
        @php
            $t = max(1, $report['completed'] + $report['in_progress'] + $report['not_started']);
            $c = round($report['completed'] / $t * 100, 2);
            $i = round($report['in_progress'] / $t * 100, 2);
        @endphp
        <div class="staff-donut-wrap">
            <div class="staff-donut" style="background: conic-gradient(#22c55e 0 {{ $c }}%, #1890ff {{ $c }}% {{ $c + $i }}%, #9ca3af {{ $c + $i }}% 100%)">
                <div class="staff-donut-inner">{{ $report['completed_pct'] }}%<br>Complete</div>
            </div>
            <ul class="staff-legend">
                <li><span class="staff-dot" style="background:#22c55e"></span> Completed: {{ $report['completed'] }}</li>
                <li><span class="staff-dot" style="background:#1890ff"></span> In Progress: {{ $report['in_progress'] }}</li>
                <li><span class="staff-dot" style="background:#9ca3af"></span> Not Started: {{ $report['not_started'] }}</li>
            </ul>
        </div>
    </div>
    <div class="staff-card">
        <div class="staff-card-header"><h2>Requirement</h2></div>
        <p class="staff-muted" style="margin:0">A scholar is marked Completed after Scholar Staff approves enough event attendance to reach {{ number_format($report['required'], 2) }} credited service hours {{ $reportFilter['semester'] === 'all' ? 'this academic year (30 per semester)' : 'this semester' }}. Pending hours do not count until you verify the record.</p>
    </div>
</section>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Scholar Completion</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Approved Hours</th>
                    <th>Pending Hours</th>
                    <th>Remaining</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td data-label="Scholar">
                            <a href="{{ route('staff.scholars.show', $row['scholar']) }}">{{ $row['scholar']->full_name }}</a>
                            <div class="staff-muted">{{ $row['scholar']->scholar_id }}</div>
                        </td>
                        <td data-label="Approved Hours">{{ number_format($row['approved'], 2) }}</td>
                        <td data-label="Pending Hours">{{ number_format($row['pending'], 2) }}</td>
                        <td data-label="Remaining">{{ number_format($row['remaining'], 2) }}</td>
                        <td data-label="Status"><span class="staff-badge {{ $row['status'] === 'Completed' ? 'green' : ($row['status'] === 'In Progress' ? 'blue' : 'gray') }}">{{ $row['status'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No scholars found for this location.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
