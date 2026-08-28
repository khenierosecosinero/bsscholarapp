@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')

@php $b = $report['breakdown']; @endphp

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved</h3><div class="value">{{ $b['approved'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending</h3><div class="value">{{ $b['pending'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✕</div><div class="staff-stat-body"><h3>Rejected</h3><div class="value">{{ $b['rejected'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">📅</div><div class="staff-stat-body"><h3>Failed Check-in</h3><div class="value">{{ $b['failedCheckIn'] }}</div></div></div>
</section>

<div class="staff-card">
    <div class="staff-card-header"><h2>Attendance Records</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Event</th>
                    <th>Check In</th>
                    <th>Hours</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $attendance)
                    <tr>
                        <td>{{ $attendance->user?->full_name ?? '—' }}</td>
                        <td>{{ $attendance->event?->title ?? '—' }}</td>
                        <td>{{ $attendance->check_in?->format('M j, Y g:i A') ?? '—' }}</td>
                        <td>{{ $attendance->hours_earned ?? 0 }}</td>
                        <td><span class="staff-badge {{ $attendance->status === 'approved' ? 'green' : ($attendance->status === 'pending' ? 'orange' : 'red') }}">{{ ucfirst(str_replace('_', ' ', $attendance->status)) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No attendance records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $attendances->links() }}</div>
</div>

@endsection
