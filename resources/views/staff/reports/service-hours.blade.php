@extends('layouts.staff')

@section('page-content')

<div style="display:flex;gap:8px;margin-bottom:20px">
    <button type="button" class="staff-btn staff-btn-primary">Weekly</button>
    <button type="button" class="staff-btn">Monthly</button>
    <button type="button" class="staff-btn">Yearly</button>
</div>

<section class="staff-grid-3">
    <div class="staff-card"><div class="staff-card-header"><h2>Hours Over Time</h2></div><div class="staff-chart-placeholder">Approved / Pending / Rejected hours trend</div></div>
    <div class="staff-card"><div class="staff-card-header"><h2>Hours by School</h2></div><div class="staff-chart-placeholder">Donut chart by school</div></div>
    <div class="staff-card"><div class="staff-card-header"><h2>Completion Status</h2></div><div class="staff-donut-wrap"><div class="staff-donut"><div class="staff-donut-inner">256<br>Total</div></div><ul class="staff-legend"><li><span class="staff-dot" style="background:#22c55e"></span> Completed</li><li><span class="staff-dot" style="background:#1890ff"></span> In Progress</li><li><span class="staff-dot" style="background:#9ca3af"></span> Not Started</li></ul></div></div>
</section>

<div class="staff-card">
    <div class="staff-card-header"><h2>Service Hours Summary by School</h2></div>
    <div class="staff-chart-placeholder">Summary table for {{ $program->location_name ?? 'program' }}</div>
</div>

@endsection
