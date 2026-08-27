@extends('layouts.staff')

@section('page-content')

<form method="GET" class="staff-filter-bar">
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by scholar name, ID or event...">
    </div>
    <button type="submit" class="staff-btn">Search</button>
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar Information</th>
                    <th>Event</th>
                    <th>Date &amp; Time</th>
                    <th>Check In / Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $attendance)
                    <tr>
                        <td>
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($attendance->user?->full_name ?? 'S', 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $attendance->user?->full_name ?? '—' }}</strong>
                                    <small>{{ $attendance->user?->scholar_id }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $attendance->event?->title ?? '—' }}</td>
                        <td>{{ $attendance->event?->starts_at?->format('M j, Y') ?? '—' }}</td>
                        <td>
                            {{ $attendance->check_in?->format('g:i A') ?? '—' }}
                            <div class="staff-muted">{{ $attendance->check_out?->format('g:i A') ?? '—' }}</div>
                        </td>
                        <td>{{ number_format((float) ($attendance->hours_earned ?? 0), 2) }}</td>
                        <td>
                            @php
                                $statusClass = match($attendance->status) {
                                    'approved' => 'green',
                                    'rejected' => 'red',
                                    default => 'orange',
                                };
                            @endphp
                            <span class="staff-badge {{ $statusClass }}">{{ ucfirst($attendance->status ?? 'pending') }}</span>
                        </td>
                        <td><span class="staff-action-btn" title="View">👁</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No attendance records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $attendances->links() }}</div>
</div>

@endsection

@push('styles')
<style>.staff-muted{color:#6b7280;font-size:13px}</style>
@endpush
