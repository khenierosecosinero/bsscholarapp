@php
    $scopeLabel = $locationLabel ?? 'All Program Types — All Locations';
    $isCityScope = ($programType ?? 'all') === 'city_municipality';
    $isProvinceScope = ($programType ?? 'all') === 'province';
@endphp

<div class="staff-card admin-scope-banner" style="margin-bottom:20px;padding:14px 18px;background:{{ $isCityScope ? '#eff6ff' : ($isProvinceScope ? '#f0fdf4' : '#fff7ed') }};border-color:{{ $isCityScope ? '#bfdbfe' : ($isProvinceScope ? '#bbf7d0' : '#fed7aa') }}">
    <strong>Current Admin Scope:</strong> {{ $scopeLabel }}
    <p class="staff-muted" style="margin:6px 0 0">
        @if($isCityScope)
            Showing City Scholarship Program records only. City and Province programs are managed separately.
        @elseif($isProvinceScope)
            Showing Province Scholarship Program records only. City and Province programs are managed separately.
        @elseif($selectedLocation ?? null)
            Showing records for {{ $selectedLocation->programTypeLabel() }}: {{ $selectedLocation->programLabel() }}.
        @else
            Showing overall statistics for all locations and scholarship programs. Select a City or Province program to view its records only.
        @endif
    </p>
</div>
