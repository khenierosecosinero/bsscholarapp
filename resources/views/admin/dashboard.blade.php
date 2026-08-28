@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')

<div class="staff-date-banner">
    <span>{{ now()->format('M j, Y') }} | {{ now()->format('l') }}</span>
</div>

<section class="staff-stat-grid">
    <div class="staff-stat-card">
        <div class="staff-stat-icon blue">👥</div>
        <div class="staff-stat-body">
            <h3>Total Scholars</h3>
            <div class="value">{{ $stats['total_scholars'] }}</div>
            <div class="sub">Active: {{ $stats['active_scholars'] ?? 0 }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon teal">🧑‍💼</div>
        <div class="staff-stat-body">
            <h3>Scholar Staff</h3>
            <div class="value">{{ $stats['total_staff'] ?? 0 }}</div>
            <div class="sub">Assigned to location</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon green">📅</div>
        <div class="staff-stat-body">
            <h3>Total Events</h3>
            <div class="value">{{ $stats['total_events'] }}</div>
            <div class="sub">Upcoming: {{ $stats['upcoming_events'] ?? 0 }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon orange">✓</div>
        <div class="staff-stat-body">
            <h3>Attendance Records</h3>
            <div class="value">{{ $stats['total_attendance'] ?? 0 }}</div>
            <div class="sub">Pending: {{ $stats['pending_attendances'] ?? 0 }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon purple">⏱</div>
        <div class="staff-stat-body">
            <h3>Service Hours</h3>
            <div class="value">{{ $stats['total_service_hours'] ?? '0.00' }}</div>
            <div class="sub">Approved hours</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon red">📄</div>
        <div class="staff-stat-body">
            <h3>Documents</h3>
            <div class="value">{{ $stats['total_documents'] ?? 0 }}</div>
            <div class="sub">Pending: {{ $stats['pending_documents'] ?? 0 }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon gray">🤝</div>
        <div class="staff-stat-body">
            <h3>Participation</h3>
            <div class="value">{{ $stats['total_participation'] ?? 0 }}</div>
            <div class="sub">Approved check-ins</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon green">🎓</div>
        <div class="staff-stat-body">
            <h3>Completed Scholars</h3>
            <div class="value">{{ $stats['completed_scholars'] ?? 0 }}</div>
            <div class="sub">Requirements met</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon orange">⏳</div>
        <div class="staff-stat-body">
            <h3>Pending Records</h3>
            <div class="value">{{ $stats['pending_records'] ?? 0 }}</div>
            <div class="sub">Needs attention</div>
        </div>
    </div>
</section>

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header">
            <h2>{{ ($isAllLocations ?? true) ? 'Location Comparison' : 'Location Overview' }}</h2>
            @if($isAllLocations ?? true)
                <a href="{{ route('admin.locations') }}" class="staff-card-link">Manage locations</a>
            @endif
        </div>
        @if($isAllLocations ?? true)
            <div class="admin-compare-grid">
                @forelse($locationSummaries as $summary)
                    @php $program = $summary['program']; @endphp
                    <div class="admin-compare-card">
                        <h3>{{ $program->programLabel() }}</h3>
                        <div class="admin-compare-stats">
                            <div><span>Scholars</span><strong>{{ $summary['scholars'] }}</strong></div>
                            <div><span>Staff</span><strong>{{ $summary['staff'] }}</strong></div>
                            <div><span>Events</span><strong>{{ $summary['events'] }}</strong></div>
                            <div><span>Pending</span><strong>{{ $summary['pending'] }}</strong></div>
                        </div>
                        <div style="margin-top:12px">
                            <a href="{{ route('admin.dashboard', ['location' => $program->id]) }}" class="staff-btn staff-btn-sm">View location</a>
                        </div>
                    </div>
                @empty
                    <p class="staff-muted">No active locations configured.</p>
                @endforelse
            </div>
        @else
            @forelse($locationSummaries as $summary)
                @php $program = $summary['program']; @endphp
                <div class="admin-compare-card">
                    <h3>{{ $program->programLabel() }}</h3>
                    <div class="admin-compare-stats">
                        <div><span>Scholars</span><strong>{{ $summary['scholars'] }}</strong></div>
                        <div><span>Staff</span><strong>{{ $summary['staff'] }}</strong></div>
                        <div><span>Events</span><strong>{{ $summary['events'] }}</strong></div>
                        <div><span>Pending</span><strong>{{ $summary['pending'] }}</strong></div>
                    </div>
                </div>
            @empty
                <p class="staff-muted">No data found for the selected location.</p>
            @endforelse
        @endif
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Quick Actions</h2>
        </div>
        <div class="staff-quick-actions">
            <a href="{{ route('admin.scholars', request()->only('location')) }}" class="staff-quick-btn blue">View Scholars</a>
            <a href="{{ route('admin.staff', request()->only('location')) }}" class="staff-quick-btn green">View Scholar Staff</a>
            <a href="{{ route('admin.events', request()->only('location')) }}" class="staff-quick-btn purple">View Events</a>
            <a href="{{ route('admin.reports', request()->only('location')) }}" class="staff-quick-btn orange">Open Reports</a>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
.staff-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
@media (max-width: 900px) { .staff-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush
