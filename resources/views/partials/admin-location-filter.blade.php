@php
    $locationKey = $locationKey ?? session('admin_location', 'all');
    $programType = $programType ?? session('admin_program_type', 'all');
    $preserve = request()->except(['location', 'program_type', 'page']);
    $groups = $locationGroups ?? ['cities' => collect(), 'provinces' => collect()];
@endphp

<div class="admin-location-banner">
    <div>
        <strong>Viewing:</strong> {{ $locationLabel ?? 'All Program Types — All Locations' }}
        <div class="staff-muted" style="margin-top:4px">City and Province Scholarship Programs are managed separately. Filter by program type and location.</div>
    </div>
    <form method="GET" action="{{ url()->current() }}" class="admin-location-switcher">
        @foreach($preserve as $key => $value)
            @if(is_array($value))
                @foreach($value as $item)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <select name="program_type" aria-label="Filter by program type">
            <option value="all" {{ $programType === 'all' ? 'selected' : '' }}>All Program Types</option>
            <option value="city_municipality" {{ $programType === 'city_municipality' ? 'selected' : '' }}>City Scholarship Programs</option>
            <option value="province" {{ $programType === 'province' ? 'selected' : '' }}>Province Scholarship Programs</option>
        </select>
        <select name="location" id="admin-location-select" aria-label="Select scholarship program">
            <option value="all" {{ $locationKey === 'all' ? 'selected' : '' }}>All Locations</option>
            @include('partials.program-select-options', [
                'programGroups' => $groups,
                'selectedId' => $locationKey === 'all' ? '' : $locationKey,
            ])
        </select>
        <button type="submit" class="staff-btn staff-btn-primary">Apply</button>
    </form>
</div>
