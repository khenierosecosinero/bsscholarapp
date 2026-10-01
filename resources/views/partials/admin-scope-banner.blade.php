@php
    $scopeLabel = $locationLabel ?? 'City Scholar — All Locations';
    $isCityScope = ($programType ?? 'city_municipality') === 'city_municipality';
    $isProvinceScope = ($programType ?? '') === 'province';
@endphp

<div class="staff-card admin-scope-banner" style="margin-bottom:20px;padding:14px 18px;background:{{ $isCityScope ? '#eff6ff' : ($isProvinceScope ? '#f0fdf4' : '#fff7ed') }};border-color:{{ $isCityScope ? '#bfdbfe' : ($isProvinceScope ? '#bbf7d0' : '#fed7aa') }}">
    <strong>Current Admin Scope:</strong> {{ $scopeLabel }}
    <p class="staff-muted" style="margin:6px 0 0">
        @if($isCityScope)
            Showing City Scholar records for the selected location. Scholarship Clubs from other provinces or municipalities/cities are not included.
        @else
            Showing Province Scholar records for the selected location. City Scholar and Province Scholar stay separate.
        @endif
    </p>
</div>
