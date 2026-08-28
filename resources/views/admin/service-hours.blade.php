@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')

@php
    $overview = $report['overview'];
    $period = $report['period'];
@endphp

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved Hours</h3><div class="value">{{ number_format($overview['approved_hours'], 2) }}</div><div class="sub">{{ $period->label ?? 'Current period' }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Hours</h3><div class="value">{{ number_format($overview['pending_hours'], 2) }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon purple">🎯</div><div class="staff-stat-body"><h3>Required</h3><div class="value">{{ $report['required'] }}</div><div class="sub">Hours per scholar</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">🎓</div><div class="staff-stat-body"><h3>Completed</h3><div class="value">{{ $report['completed'] }}</div><div class="sub">Scholars met requirement</div></div></div>
</section>

<div class="staff-card">
    <div class="staff-card-header"><h2>Scholar Service Hours</h2></div>
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
                        <td>{{ $row['scholar']->full_name }}</td>
                        <td>{{ $row['scholar']->scholarshipProgram?->programLabel() ?? '—' }}</td>
                        <td>{{ number_format($row['approved'], 2) }}</td>
                        <td>{{ number_format($row['pending'], 2) }}</td>
                        <td>{{ number_format($row['remaining'], 2) }}</td>
                        <td><span class="staff-badge {{ $row['status'] === 'Completed' ? 'green' : ($row['status'] === 'In Progress' ? 'orange' : 'gray') }}">{{ $row['status'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No service hour records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
