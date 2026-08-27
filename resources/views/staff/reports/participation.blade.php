@extends('layouts.staff')

@section('page-content')

<div style="display:flex;gap:8px;margin-bottom:20px">
    <button type="button" class="staff-btn staff-btn-primary">Weekly</button>
    <button type="button" class="staff-btn">Monthly</button>
    <button type="button" class="staff-btn">Yearly</button>
</div>

<section class="staff-grid-3">
    <div class="staff-card"><div class="staff-card-header"><h2>Participation Overview</h2></div><div class="staff-donut-wrap"><div class="staff-donut"><div class="staff-donut-inner">76.8%<br>Rate</div></div><ul class="staff-legend"><li><span class="staff-dot" style="background:#22c55e"></span> Participated</li><li><span class="staff-dot" style="background:#1890ff"></span> No Show</li><li><span class="staff-dot" style="background:#f59e0b"></span> Registered</li></ul></div></div>
    <div class="staff-card"><div class="staff-card-header"><h2>Participation by School</h2></div><div class="staff-chart-placeholder">Donut chart by school</div></div>
    <div class="staff-card"><div class="staff-card-header"><h2>Participation by Event Type</h2></div><div class="staff-chart-placeholder">Event type breakdown</div></div>
</section>

<div class="staff-card">
    <div class="staff-card-header"><h2>Participation Trend</h2></div>
    <div class="staff-chart-placeholder">Weekly participation trend</div>
</div>

@endsection
