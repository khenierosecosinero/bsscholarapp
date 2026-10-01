@php
    $locationKey = $locationKey ?? session('admin_location', 'all');
    $programType = $programType ?? session('admin_program_type', 'city_municipality');
    if ($programType === 'all') {
        $programType = 'city_municipality';
    }
    $tree = $locationTree ?? [];
    $address = $selectedAddress ?? ['province' => null, 'city' => null];
    $selectedProvince = $address['province'] ?? '';
    $selectedCity = $address['city'] ?? '';
    $isCityScholar = $programType === 'city_municipality';
    $showCategory = $showCategory ?? true;
    $cityEnabled = $showCategory ? $isCityScholar : true;
@endphp

<div class="admin-location-cascade" data-admin-location-cascade>
    @if($showCategory)
        <div class="admin-location-cascade-field">
            <label class="admin-location-cascade-label" for="{{ $idPrefix ?? 'admin' }}-program-type">Scholarship category</label>
            <select name="program_type" id="{{ $idPrefix ?? 'admin' }}-program-type" data-admin-program-type aria-label="Scholarship category">
                <option value="city_municipality" {{ $isCityScholar ? 'selected' : '' }}>City Scholar</option>
                <option value="province" {{ ! $isCityScholar ? 'selected' : '' }}>Province Scholar</option>
            </select>
        </div>
    @endif
    <div class="admin-location-cascade-field">
        <label class="admin-location-cascade-label" for="{{ $idPrefix ?? 'admin' }}-province">Province</label>
        <select id="{{ $idPrefix ?? 'admin' }}-province" data-admin-province aria-label="Province">
            <option value="">Select province</option>
            @foreach($tree as $province)
                <option
                    value="{{ $province['name'] }}"
                    data-id="{{ $province['id'] ?? '' }}"
                    @selected($selectedProvince === ($province['name'] ?? ''))
                >{{ $province['name'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="admin-location-cascade-field">
        <label class="admin-location-cascade-label" for="{{ $idPrefix ?? 'admin' }}-city">Municipality / City</label>
        <select id="{{ $idPrefix ?? 'admin' }}-city" data-admin-city aria-label="Municipality or City" @disabled(! $cityEnabled)>
            <option value="">{{ $cityEnabled ? 'Select municipality or city' : 'Not used for Province Scholar' }}</option>
        </select>
    </div>
    <input type="hidden" name="location" data-admin-location-value value="{{ $locationKey }}">
    <script type="application/json" data-admin-location-tree>{!! json_encode($tree, JSON_UNESCAPED_UNICODE) !!}</script>
    <input type="hidden" data-admin-selected-city value="{{ $selectedCity }}">
</div>
