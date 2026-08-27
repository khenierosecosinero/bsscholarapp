@extends('layouts.staff')

@section('page-content')

<div style="display:flex;gap:8px;margin-bottom:20px">
    <button type="button" class="staff-btn staff-btn-primary">Weekly</button>
    <button type="button" class="staff-btn">Monthly</button>
    <button type="button" class="staff-btn">Yearly</button>
</div>

<section class="staff-grid-3">
    <div class="staff-card"><div class="staff-card-header"><h2>Completion Overview</h2></div><div class="staff-donut-wrap"><div class="staff-donut"><div class="staff-donut-inner">64.3%<br>Rate</div></div><ul class="staff-legend"><li><span class="staff-dot" style="background:#22c55e"></span> Completed</li><li><span class="staff-dot" style="background:#1890ff"></span> In Progress</li><li><span class="staff-dot" style="background:#f59e0b"></span> Pending</li></ul></div></div>
    <div class="staff-card"><div class="staff-card-header"><h2>Completion by School</h2></div><div class="staff-chart-placeholder">Donut chart by school</div></div>
    <div class="staff-card"><div class="staff-card-header"><h2>Completion by Status</h2></div><div class="staff-chart-placeholder">Status breakdown chart</div></div>
</section>

<div class="staff-card">
    <div class="staff-card-header"><h2>Completion Trend</h2></div>
    <div class="staff-chart-placeholder">Weekly completion trend</div>
</div>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Completion Rate by School</h2></div>
    <div class="staff-chart-placeholder">Bar chart by school</div>
</div>

@endsection
