@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')
@include('partials.admin-scope-banner')

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">📝</div><div class="staff-stat-body"><h3>Registered</h3><div class="value">{{ $report['registered'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Participated</h3><div class="value">{{ $report['participated'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending</h3><div class="value">{{ $report['pending'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✕</div><div class="staff-stat-body"><h3>Failed Check-in</h3><div class="value">{{ $report['failed'] }}</div></div></div>
</section>

<div class="staff-card">
    <div class="staff-card-header"><h2>Participation by Event</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Scholar Program</th>
                    <th>Program Type</th>
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
                        <td>{{ $event->title }}</td>
                        <td>{{ $event->scholarshipProgram?->programLabel() ?? '—' }}</td>
                        <td>{{ $event->scholarshipProgram?->programTypeLabel() ?? '—' }}</td>
                        <td>{{ $event->starts_at?->format('M j, Y') ?? '—' }}</td>
                        <td>{{ $event->registrations_count }}</td>
                        <td>{{ $event->checked_in_count }}</td>
                        <td>{{ $event->approved_count }}</td>
                        <td>{{ number_format((float) $event->service_hours, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">No events found for this scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
