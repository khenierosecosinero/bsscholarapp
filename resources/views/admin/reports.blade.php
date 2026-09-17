@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')
@include('partials.admin-scope-banner')

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">👥</div><div class="staff-stat-body"><h3>Scholars</h3><div class="value">{{ $stats['total_scholars'] }}</div><div class="sub">Active: {{ $stats['active_scholars'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">🧑‍💼</div><div class="staff-stat-body"><h3>Staff</h3><div class="value">{{ $stats['total_staff'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">🎓</div><div class="staff-stat-body"><h3>Completed</h3><div class="value">{{ $completionReport['completed'] }}</div><div class="sub">{{ $completionReport['completed_pct'] }}% completion</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">🤝</div><div class="staff-stat-body"><h3>Participation</h3><div class="value">{{ $participationReport['participated'] }}</div><div class="sub">Registered: {{ $participationReport['registered'] }}</div></div></div>
</section>

@if(!($isAllLocations ?? true))
    @include('partials.admin-report-pies')
@endif

<div class="staff-card">
    <div class="staff-card-header"><h2>Attendance Report</h2></div>
    @php $b = $attendanceReport['breakdown']; @endphp
    <ul class="staff-legend">
        <li><span class="staff-dot" style="background:#22c55e"></span> Approved: {{ $b['approved'] }}</li>
        <li><span class="staff-dot" style="background:#f59e0b"></span> Pending: {{ $b['pending'] }}</li>
        <li><span class="staff-dot" style="background:#ef4444"></span> Rejected: {{ $b['rejected'] }}</li>
        <li><span class="staff-dot" style="background:#991b1b"></span> Failed: {{ $b['failedCheckIn'] }}</li>
    </ul>
</div>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Completion Status</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    @if($isAllLocations ?? true)
                        <th>Scholar Program</th>
                        <th>Program Type</th>
                    @endif
                    <th>Approved Hours</th>
                    <th>Required</th>
                    <th>Progress</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($completionReport['rows'] as $row)
                    <tr>
                        <td>{{ $row['scholar']->full_name }}</td>
                        @if($isAllLocations ?? true)
                            <td>{{ $row['scholar']->scholarshipProgram?->programLabel() ?? '—' }}</td>
                            <td>{{ $row['scholar']->scholarshipProgram?->programTypeLabel() ?? '—' }}</td>
                        @endif
                        <td>{{ number_format($row['approved'], 2) }}</td>
                        <td>{{ $completionReport['required'] }}</td>
                        <td>{{ number_format($row['remaining'], 2) }} remaining</td>
                        <td><span class="staff-badge {{ $row['status'] === 'Completed' ? 'green' : ($row['status'] === 'In Progress' ? 'orange' : 'gray') }}">{{ $row['status'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ ($isAllLocations ?? true) ? 7 : 5 }}">No completion data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
