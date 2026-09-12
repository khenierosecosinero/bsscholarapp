@extends('layouts.admin')

@section('page-content')

@include('partials.admin-scope-banner')
@include('partials.admin-dashboard-scope-select')

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
        <div class="staff-stat-icon green">✓</div>
        <div class="staff-stat-body">
            <h3>Approved Scholars</h3>
            <div class="value">{{ $stats['active_scholars'] ?? 0 }}</div>
            <div class="sub">Active accounts</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon orange">⏳</div>
        <div class="staff-stat-body">
            <h3>Pending Scholars</h3>
            <div class="value">{{ $stats['pending_scholars'] ?? 0 }}</div>
            <div class="sub">Awaiting approval</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon red">✕</div>
        <div class="staff-stat-body">
            <h3>Rejected Scholars</h3>
            <div class="value">{{ $stats['rejected_scholars'] ?? 0 }}</div>
            <div class="sub">Denied registrations</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon teal">🧑‍💼</div>
        <div class="staff-stat-body">
            <h3>Scholar Staff</h3>
            <div class="value">{{ $stats['total_staff'] ?? 0 }}</div>
            <div class="sub">Approved staff</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon orange">🧑‍💼</div>
        <div class="staff-stat-body">
            <h3>Pending Staff</h3>
            <div class="value">{{ $stats['pending_staff'] ?? 0 }}</div>
            <div class="sub">Awaiting admin approval</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon green">📅</div>
        <div class="staff-stat-body">
            <h3>Events</h3>
            <div class="value">{{ $stats['total_events'] }}</div>
            <div class="sub">Upcoming: {{ $stats['upcoming_events'] ?? 0 }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon orange">✓</div>
        <div class="staff-stat-body">
            <h3>Attendance</h3>
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
</section>

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Quick Actions</h2>
        </div>
        <div class="staff-quick-actions">
            <a href="{{ route('admin.scholars', array_filter(['location' => $locationKey !== 'all' ? $locationKey : null, 'program_type' => ($programType ?? 'all') !== 'all' ? $programType : null])) }}" class="staff-quick-btn blue">View Scholars</a>
            <a href="{{ route('admin.staff', array_filter(['location' => $locationKey !== 'all' ? $locationKey : null, 'program_type' => ($programType ?? 'all') !== 'all' ? $programType : null])) }}" class="staff-quick-btn green">View Scholar Staff</a>
            <a href="{{ route('admin.events', array_filter(['location' => $locationKey !== 'all' ? $locationKey : null, 'program_type' => ($programType ?? 'all') !== 'all' ? $programType : null])) }}" class="staff-quick-btn purple">View Events</a>
            <a href="{{ route('admin.reports', array_filter(['location' => $locationKey !== 'all' ? $locationKey : null, 'program_type' => ($programType ?? 'all') !== 'all' ? $programType : null])) }}" class="staff-quick-btn orange">Open Reports</a>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
.staff-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.admin-dashboard-scope-card { margin-bottom: 20px; }
.admin-dashboard-scope-copy { margin: 0 0 14px; }
.admin-dashboard-scope-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #5c1a14;
    margin-bottom: 8px;
}
.admin-dashboard-scope-form select {
    width: 100%;
    max-width: 520px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
}
@media (max-width: 900px) { .staff-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('admin-dashboard-scope-form');
    var select = document.getElementById('admin-dashboard-location-select');
    var typeInput = document.getElementById('dashboard-program-type');
    if (!form || !select) return;

    select.addEventListener('change', function () {
        var option = this.options[this.selectedIndex];
        if (typeInput) {
            typeInput.value = option.getAttribute('data-program-type') || 'all';
        }
        form.submit();
    });
});
</script>
@endpush
