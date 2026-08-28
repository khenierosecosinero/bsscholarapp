@extends('layouts.admin')

@section('page-content')

<section class="staff-stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">🗺</div><div class="staff-stat-body"><h3>Active Locations</h3><div class="value">{{ $summaries->count() }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">👥</div><div class="staff-stat-body"><h3>Total Scholars</h3><div class="value">{{ $summaries->sum('scholars') }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">🧑‍💼</div><div class="staff-stat-body"><h3>Total Staff</h3><div class="value">{{ $summaries->sum('staff') }}</div></div></div>
</section>

<div class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header">
            <h2>All Locations</h2>
        </div>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Scholar Program</th>
                        <th>Type</th>
                        <th>Scholars</th>
                        <th>Staff</th>
                        <th>Events</th>
                        <th>Pending</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaries as $summary)
                        @php $program = $summary['program']; @endphp
                        <tr>
                            <td><strong>{{ $program->programLabel() }}</strong></td>
                            <td>{{ $program->programTypeLabel() }}</td>
                            <td>{{ $summary['scholars'] }}</td>
                            <td>{{ $summary['staff'] }}</td>
                            <td>{{ $summary['events'] }}</td>
                            <td>{{ $summary['pending'] }}</td>
                            <td>
                                <a href="{{ route('admin.dashboard', ['location' => $program->id]) }}" class="staff-btn staff-btn-sm">Dashboard</a>
                                <a href="{{ route('admin.locations.edit', $program) }}" class="staff-btn staff-btn-sm">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No locations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Add Location</h2>
        </div>
        <form method="POST" action="{{ route('admin.locations.store') }}">
            @csrf
            <div class="staff-form-group">
                <label for="location_name">Location Name</label>
                <input type="text" id="location_name" name="location_name" value="{{ old('location_name') }}" required>
            </div>
            <div class="staff-form-group">
                <label for="display_name">Display Name</label>
                <input type="text" id="display_name" name="display_name" value="{{ old('display_name') }}">
            </div>
            <div class="staff-form-group">
                <label for="location_type">Location Type</label>
                <select id="location_type" name="location_type" required>
                    <option value="city_municipality">City / Municipality</option>
                    <option value="province">Province</option>
                </select>
            </div>
            <div class="staff-form-group">
                <label for="province_name">Province</label>
                <input type="text" id="province_name" name="province_name" value="{{ old('province_name', 'Surigao del Norte') }}">
            </div>
            <div class="staff-form-group">
                <label for="region_name">Region</label>
                <input type="text" id="region_name" name="region_name" value="{{ old('region_name', 'Caraga') }}">
            </div>
            <div class="admin-form-actions">
                <button type="submit" class="staff-btn staff-btn-primary">Add Location</button>
            </div>
        </form>
    </div>
</div>

@endsection
