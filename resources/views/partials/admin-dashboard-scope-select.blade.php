@php
    $locationKey = $locationKey ?? session('admin_location', 'all');
    $programType = $programType ?? session('admin_program_type', 'all');
    $groups = $programGroups ?? $locationGroups ?? ['cities' => collect(), 'provinces' => collect()];
    $selectedProgramType = $selectedLocation
        ? ($selectedLocation->isCityProgram() ? 'city_municipality' : 'province')
        : 'all';
@endphp

<div class="staff-card admin-dashboard-scope-card">
    <div class="staff-card-header">
        <h2>Location &amp; Scholarship Program</h2>
    </div>
    <p class="staff-muted admin-dashboard-scope-copy">
        Choose a location and scholarship program to view its dashboard statistics.
        City Scholarship Programs and Province Scholarship Programs are kept separate.
    </p>
    <form method="GET" action="{{ route('admin.dashboard') }}" class="admin-dashboard-scope-form" id="admin-dashboard-scope-form">
        <input type="hidden" name="program_type" id="dashboard-program-type" value="{{ $selectedProgramType }}">
        <label class="admin-dashboard-scope-label" for="admin-dashboard-location-select">Location and program</label>
        <select name="location" id="admin-dashboard-location-select" aria-label="Select location and scholarship program">
            <option value="all" data-program-type="all" {{ $locationKey === 'all' ? 'selected' : '' }}>
                All Locations / All Programs
            </option>
            @include('partials.program-select-options', [
                'programGroups' => $groups,
                'selectedId' => $locationKey === 'all' ? '' : $locationKey,
            ])
        </select>
    </form>
</div>
