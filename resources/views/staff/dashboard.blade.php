@extends('layouts.staff')

@section('page-content')

<div class="staff-date-banner">
    <span>{{ now()->format('M j, Y') }} | {{ now()->format('l') }}</span>
</div>

<section class="staff-stat-grid">
    <div class="staff-stat-card">
        <div class="staff-stat-icon blue">👥</div>
        <div class="staff-stat-body">
            <h3>Total Scholars</h3>
            <div class="value">{{ $stats['total_scholars'] }}</div>
            <div class="sub">Active Scholars: {{ $stats['active_scholars'] }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon green">📅</div>
        <div class="staff-stat-body">
            <h3>Total Events</h3>
            <div class="value">{{ $stats['total_events'] }}</div>
            <div class="sub">Upcoming: {{ $stats['upcoming_events'] }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon orange">⏱</div>
        <div class="staff-stat-body">
            <h3>Attendance (Pending)</h3>
            <div class="value">{{ $stats['pending_attendances'] }}</div>
            <div class="sub">For verification</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon purple">✓</div>
        <div class="staff-stat-body">
            <h3>Total Service Hours</h3>
            <div class="value">{{ $stats['total_service_hours'] }}</div>
            <div class="sub">All semesters</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon teal">📄</div>
        <div class="staff-stat-body">
            <h3>Documents Pending</h3>
            <div class="value">{{ $stats['pending_documents'] }}</div>
            <div class="sub">For review</div>
        </div>
    </div>
</section>

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Pending Approvals</h2>
            <a href="{{ route('staff.approval-requests') }}" class="staff-card-link">View all</a>
        </div>
        @if($pendingApprovals->isEmpty())
            <p class="staff-muted">No pending scholar account approvals.</p>
        @else
            <div class="staff-table-wrap">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Name / Title</th>
                            <th>Details</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingApprovals as $request)
                            <tr>
                                <td>Registration</td>
                                <td>{{ $request->full_name }}</td>
                                <td>{{ $request->scholar_id }} · {{ $request->email }}</td>
                                <td>{{ $request->created_at->format('M j, Y') }}</td>
                                <td>
                                    <a href="{{ route('staff.scholars.show', $request) }}" class="staff-action-btn" title="View">👁</a>
                                    <form method="POST" action="{{ route('staff.scholars.approve', $request) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="staff-action-btn" title="Approve">✓</button>
                                    </form>
                                    <form method="POST" action="{{ route('staff.scholars.reject', $request) }}" style="display:inline" onsubmit="return confirm('Reject and permanently delete this account? This cannot be undone.');">
                                        @csrf
                                        <button type="submit" class="staff-action-btn" title="Reject and delete">✕</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Attendance Status Overview</h2>
        </div>
        <div class="staff-donut-wrap">
            <div class="staff-donut">
                <div class="staff-donut-inner">{{ $attendanceBreakdown['total'] }}<br>Total</div>
            </div>
            <ul class="staff-legend">
                <li><span class="staff-dot" style="background:#1890ff"></span> Approved: {{ $attendanceBreakdown['approved'] }}</li>
                <li><span class="staff-dot" style="background:#f59e0b"></span> Pending: {{ $attendanceBreakdown['pending'] }}</li>
                <li><span class="staff-dot" style="background:#ef4444"></span> Rejected: {{ $attendanceBreakdown['rejected'] }}</li>
                <li><span class="staff-dot" style="background:#991b1b"></span> Failed to Check In: {{ $attendanceBreakdown['failedCheckIn'] ?? 0 }}</li>
            </ul>
        </div>
    </div>
</section>

<section class="staff-grid-3">
    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Service Hours Overview</h2>
        </div>
        <div class="staff-chart-placeholder" style="height:auto;padding:20px;display:block">
            <div class="staff-list-item"><div><strong>{{ number_format($hoursOverview['approved_hours'], 2) }} hrs</strong><div class="staff-muted">Approved and credited</div></div></div>
            <div class="staff-list-item"><div><strong>{{ number_format($hoursOverview['pending_hours'], 2) }} hrs</strong><div class="staff-muted">Pending Scholar Staff verification</div></div></div>
            <div class="staff-list-item"><div><strong>{{ $hoursOverview['failed_count'] }} records</strong><div class="staff-muted">Failed to check in · 0 hours</div></div></div>
            <a href="{{ route('staff.reports.service-hours') }}" class="staff-card-link">Open service hours report</a>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Recent Activities</h2>
        </div>
        @forelse($recentActivities as $activity)
            <div class="staff-list-item">
                <div>
                    <strong>{{ $activity->user?->full_name ?? 'Scholar' }}</strong>
                    <div class="staff-muted">{{ $activity->description }}</div>
                    <small class="staff-muted">{{ $activity->created_at->diffForHumans() }}</small>
                </div>
            </div>
        @empty
            <p class="staff-muted">No recent activity recorded.</p>
        @endforelse
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Upcoming Events</h2>
            <a href="{{ route('staff.events') }}" class="staff-card-link">View all</a>
        </div>
        @forelse($upcomingEvents as $event)
            <div class="staff-list-item">
                <div class="staff-date-box">
                    <div class="month">{{ $event->starts_at->format('M') }}</div>
                    <div class="day">{{ $event->starts_at->format('d') }}</div>
                </div>
                <div>
                    <strong>{{ $event->title }}</strong>
                    <div class="staff-muted">{{ $event->starts_at->format('g:i A') }} · {{ $event->location }}</div>
                </div>
            </div>
        @empty
            <p class="staff-muted">No upcoming events scheduled.</p>
        @endforelse

        <div class="staff-quick-actions" style="margin-top:16px">
            <a href="{{ route('staff.events') }}" class="staff-quick-btn blue">Add New Event <span>›</span></a>
            <a href="{{ route('staff.attendance') }}" class="staff-quick-btn green">Activate Attendance <span>›</span></a>
            <a href="{{ route('staff.documents') }}" class="staff-quick-btn purple">Approve Documents <span>›</span></a>
            <a href="{{ route('staff.reports.service-hours') }}" class="staff-quick-btn orange">Generate Reports <span>›</span></a>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>.staff-date-banner{margin-bottom:16px;font-size:13px;color:#6b7280;text-align:right}.staff-muted{color:#6b7280;font-size:13px}</style>
@endpush
