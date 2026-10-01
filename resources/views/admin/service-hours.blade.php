@extends('layouts.admin')

@section('page-content')

@include('partials.admin-hours-filter')

@php
    $overview = $report['overview'];
    $period = $report['period'];
    $locationOf = function ($scholar) {
        $parts = array_filter([
            $scholar->city ?: $scholar->municipalityName(),
            $scholar->province ?: $scholar->provinceName(),
        ]);

        return $parts !== [] ? implode(', ', $parts) : '—';
    };
@endphp

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved Hours</h3><div class="value">{{ number_format($overview['approved_hours'], 2) }}</div><div class="sub">{{ is_array($period) ? ($period['label'] ?? $period['academic_year'] ?? 'Current period') : ($period->label ?? 'Current period') }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Hours</h3><div class="value">{{ number_format($overview['pending_hours'], 2) }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon purple">🎯</div><div class="staff-stat-body"><h3>Required</h3><div class="value">{{ $report['required'] }}</div><div class="sub">Hours per scholar</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">🎓</div><div class="staff-stat-body"><h3>Completed</h3><div class="value">{{ $report['completed'] }}</div><div class="sub">Scholars met requirement</div></div></div>
</section>

<div class="staff-card">
    <div class="staff-card-header"><h2>Scholar Service Hours</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Scholarship Club</th>
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
                        <td data-label="Scholar">{{ $row['scholar']->full_name }}</td>
                        <td data-label="Scholarship Club">{{ $row['scholar']->scholarshipClub?->name ?: '—' }}</td>
                        <td data-label="Location">{{ $locationOf($row['scholar']) }}</td>
                        <td data-label="Approved">{{ number_format($row['approved'], 2) }}</td>
                        <td data-label="Pending">{{ number_format($row['pending'], 2) }}</td>
                        <td data-label="Remaining">{{ number_format($row['remaining'], 2) }}</td>
                        <td data-label="Status"><span class="staff-badge {{ $row['status'] === 'Completed' ? 'green' : ($row['status'] === 'In Progress' ? 'orange' : 'gray') }}">{{ $row['status'] }}</span></td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="7">No records found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
