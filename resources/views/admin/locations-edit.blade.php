@extends('layouts.admin')

@section('page-content')

<div class="staff-card" style="margin-bottom:20px">
    <div class="staff-card-header">
        <h2>Edit Location: {{ $location->display_name ?: $location->location_name }}</h2>
        <a href="{{ route('admin.locations') }}" class="staff-card-link">Back to locations</a>
    </div>
    <form method="POST" action="{{ route('admin.locations.update', $location) }}">
        @csrf
        @method('PUT')
        <div class="staff-form-grid">
            <div class="staff-form-group">
                <label>Location Name</label>
                <input type="text" value="{{ $location->location_name }}" readonly>
            </div>
            <div class="staff-form-group">
                <label for="display_name">Display Name</label>
                <input type="text" id="display_name" name="display_name" value="{{ old('display_name', $location->display_name) }}">
            </div>
            <div class="staff-form-group">
                <label for="is_active">Status</label>
                <select id="is_active" name="is_active">
                    <option value="1" {{ old('is_active', $location->is_active) ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ !old('is_active', $location->is_active) ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="staff-btn staff-btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">👥</div><div class="staff-stat-body"><h3>Scholars</h3><div class="value">{{ $stats['total_scholars'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">🧑‍💼</div><div class="staff-stat-body"><h3>Staff</h3><div class="value">{{ $stats['total_staff'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">📅</div><div class="staff-stat-body"><h3>Events</h3><div class="value">{{ $stats['total_events'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏳</div><div class="staff-stat-body"><h3>Pending</h3><div class="value">{{ $stats['pending_records'] ?? 0 }}</div></div></div>
</section>

<div class="staff-quick-actions" style="margin-top:20px">
    <a href="{{ route('admin.dashboard', ['location' => $location->id]) }}" class="staff-quick-btn blue">Open Location Dashboard</a>
    <a href="{{ route('admin.scholars', ['location' => $location->id]) }}" class="staff-quick-btn green">View Scholars</a>
    <a href="{{ route('admin.reports', ['location' => $location->id]) }}" class="staff-quick-btn orange">Location Reports</a>
</div>

@endsection
