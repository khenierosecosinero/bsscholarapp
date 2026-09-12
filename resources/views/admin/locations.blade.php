@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')
@include('partials.admin-scope-banner')

<section class="staff-stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">🏙</div><div class="staff-stat-body"><h3>City Programs</h3><div class="value">{{ $citySummaries->count() }}</div><div class="sub">{{ $citySummaries->sum('scholars') }} scholars</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon teal">🗺</div><div class="staff-stat-body"><h3>Province Programs</h3><div class="value">{{ $provinceSummaries->count() }}</div><div class="sub">{{ $provinceSummaries->sum('scholars') }} scholars</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">📍</div><div class="staff-stat-body"><h3>In Current Filter</h3><div class="value">{{ $summaries->count() }}</div><div class="sub">{{ $summaries->sum('scholars') }} scholars</div></div></div>
</section>

<div class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header"><h2>City Scholarship Programs</h2></div>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Scholars</th>
                        <th>Staff</th>
                        <th>Events</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($citySummaries as $summary)
                        @php $program = $summary['program']; @endphp
                        <tr>
                            <td><strong>{{ $program->programLabel() }}</strong></td>
                            <td>{{ $summary['scholars'] }}</td>
                            <td>{{ $summary['staff'] }}</td>
                            <td>{{ $summary['events'] }}</td>
                            <td>
                                <a href="{{ route('admin.dashboard', ['location' => $program->id, 'program_type' => 'city_municipality']) }}" class="staff-btn staff-btn-sm">Manage</a>
                                <a href="{{ route('admin.locations.edit', $program) }}" class="staff-btn staff-btn-sm">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No city programs configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header"><h2>Province Scholarship Programs</h2></div>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Scholars</th>
                        <th>Staff</th>
                        <th>Events</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($provinceSummaries as $summary)
                        @php $program = $summary['program']; @endphp
                        <tr>
                            <td><strong>{{ $program->programLabel() }}</strong></td>
                            <td>{{ $summary['scholars'] }}</td>
                            <td>{{ $summary['staff'] }}</td>
                            <td>{{ $summary['events'] }}</td>
                            <td>
                                <a href="{{ route('admin.dashboard', ['location' => $program->id, 'program_type' => 'province']) }}" class="staff-btn staff-btn-sm">Manage</a>
                                <a href="{{ route('admin.locations.edit', $program) }}" class="staff-btn staff-btn-sm">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No province programs configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Add Scholarship Program Location</h2></div>
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
            <label for="location_type">Program Type</label>
            <select id="location_type" name="location_type" required>
                <option value="city_municipality">City Scholarship Program</option>
                <option value="province">Province Scholarship Program</option>
            </select>
        </div>
        <div class="staff-form-group">
            <label for="province_name">Province (for city programs)</label>
            <input type="text" id="province_name" name="province_name" value="{{ old('province_name', 'Surigao del Norte') }}">
        </div>
        <div class="staff-form-group">
            <label for="region_name">Region</label>
            <input type="text" id="region_name" name="region_name" value="{{ old('region_name', 'Caraga') }}">
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="staff-btn staff-btn-primary">Add Program Location</button>
        </div>
    </form>
</div>

@endsection
