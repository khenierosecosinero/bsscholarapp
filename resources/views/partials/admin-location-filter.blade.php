@php
    $locationKey = $locationKey ?? session('admin_location', 'all');
    $preserve = request()->except(['location', 'page']);
    $groups = $locationGroups ?? ['cities' => collect(), 'provinces' => collect()];
@endphp

<div class="admin-location-banner">
    <div>
        <strong>Viewing:</strong> {{ $locationLabel ?? 'Overall / All Locations' }}
        <div class="staff-muted" style="margin-top:4px">Switch between system-wide and location-specific scholar programs.</div>
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
        <select name="location" id="admin-location-select" aria-label="Select scholar program">
            <option value="all" {{ $locationKey === 'all' ? 'selected' : '' }}>Overall / All Locations</option>
            @include('partials.program-select-options', [
                'programGroups' => $groups,
                'selectedId' => $locationKey === 'all' ? '' : $locationKey,
            ])
        </select>
        <button type="submit" class="staff-btn staff-btn-primary">Apply</button>
    </form>
</div>
