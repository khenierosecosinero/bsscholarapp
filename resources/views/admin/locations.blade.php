@extends('layouts.admin')

@section('page-content')

@php
    $listType = $listType ?? (($programType ?? 'all') === 'province' ? 'province' : 'city_municipality');
    $isCityList = $listType === 'city_municipality';
    $listTitle = $isCityList ? 'City Scholarship Programs' : 'Province Scholarship Programs';
    $listProgramType = $isCityList ? 'city_municipality' : 'province';
    $emptyCopy = $isCityList ? 'No city programs configured.' : 'No province programs configured.';
@endphp

<div class="admin-locations-page">
@include('partials.admin-scope-banner')

<section class="staff-stat-grid staff-stat-grid-3">
    <div class="staff-stat-card">
        <div class="staff-stat-icon blue">🏙</div>
        <div class="staff-stat-body">
            <h3>City Programs</h3>
            <div class="value">{{ $cityCount }}</div>
            <div class="sub">{{ $cityScholarCount }} scholars</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon teal">🗺</div>
        <div class="staff-stat-body">
            <h3>Province Programs</h3>
            <div class="value">{{ $provinceCount }}</div>
            <div class="sub">{{ $provinceScholarCount }} scholars</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon green">📍</div>
        <div class="staff-stat-body">
            <h3>In Current Filter</h3>
            <div class="value">{{ $summaries->count() }}</div>
            <div class="sub">{{ $summaries->sum('scholars') }} scholars</div>
        </div>
    </div>
</section>

<div class="staff-card admin-program-type-card">
    <div class="staff-card-header">
        <h2>Scholarship Program Type</h2>
    </div>
    <p class="staff-muted admin-program-type-copy">Choose a category to view that list only. City and Province programs are not shown together.</p>
    <div class="admin-program-type-switch" role="tablist" aria-label="Scholarship Program Type">
        <a
            href="{{ route('admin.locations', ['program_type' => 'city_municipality', 'location' => 'all']) }}"
            class="staff-btn admin-program-type-btn {{ $isCityList ? 'staff-btn-primary' : '' }}"
            role="tab"
            aria-selected="{{ $isCityList ? 'true' : 'false' }}"
        >City Scholarship Programs</a>
        <a
            href="{{ route('admin.locations', ['program_type' => 'province', 'location' => 'all']) }}"
            class="staff-btn admin-program-type-btn {{ $isCityList ? '' : 'staff-btn-primary' }}"
            role="tab"
            aria-selected="{{ $isCityList ? 'false' : 'true' }}"
        >Province Scholarship Programs</a>
    </div>
</div>

<div class="staff-card admin-locations-table">
    <div class="staff-card-header"><h2>{{ $listTitle }}</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table">
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
                @forelse($summaries as $summary)
                    @php $program = $summary['program']; @endphp
                    <tr>
                        <td data-label="Program"><strong>{{ $program->programLabel() }}</strong></td>
                        <td data-label="Scholars">{{ $summary['scholars'] }}</td>
                        <td data-label="Staff">{{ $summary['staff'] }}</td>
                        <td data-label="Events">{{ $summary['events'] }}</td>
                        <td data-label="Actions">
                            <div class="staff-action-group">
                                <a href="{{ route('admin.dashboard', ['location' => $program->id, 'program_type' => $listProgramType]) }}" class="staff-btn staff-btn-sm">Manage</a>
                                <a href="{{ route('admin.locations.edit', $program) }}" class="staff-btn staff-btn-sm">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="5">{{ $emptyCopy }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="staff-card admin-locations-add">
    <div class="staff-card-header"><h2>Add Scholarship Program Location</h2></div>
    <form method="POST" action="{{ route('admin.locations.store') }}">
        @csrf
        <div class="staff-form-grid">
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
                    <option value="city_municipality" {{ old('location_type', $listProgramType) === 'city_municipality' ? 'selected' : '' }}>City Scholarship Program</option>
                    <option value="province" {{ old('location_type', $listProgramType) === 'province' ? 'selected' : '' }}>Province Scholarship Program</option>
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
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="staff-btn staff-btn-primary">Add Program Location</button>
        </div>
    </form>
</div>
</div>

@endsection
