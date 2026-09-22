@extends('layouts.admin')

@section('page-content')

@include('partials.admin-scope-banner')

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">👥</div><div class="staff-stat-body"><h3>Scholars</h3><div class="value">{{ $stats['total_scholars'] }}</div><div class="sub">Active: {{ $stats['active_scholars'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">🧑‍💼</div><div class="staff-stat-body"><h3>Staff</h3><div class="value">{{ $stats['total_staff'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">🎓</div><div class="staff-stat-body"><h3>Completed</h3><div class="value">{{ $completionReport['completed'] }}</div><div class="sub">{{ $completionReport['completed_pct'] }}% completion</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">🤝</div><div class="staff-stat-body"><h3>Participation</h3><div class="value">{{ $participationReport['participated'] }}</div><div class="sub">Registered: {{ $participationReport['registered'] }}</div></div></div>
</section>

@include('partials.admin-report-pies')

@endsection
