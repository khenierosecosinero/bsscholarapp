@php
    $locationKey = $locationKey ?? session('admin_location', 'all');
    $programType = $programType ?? session('admin_program_type', 'city_municipality');
    $preserve = request()->except(['location', 'program_type', 'page']);
@endphp

<div class="admin-location-banner">
    <div>
        <strong>Viewing:</strong> {{ $locationLabel ?? 'City Scholar — All Locations' }}
        <div class="staff-muted" style="margin-top:4px">City Scholar and Province Scholar stay separate. Scholarship Clubs follow the selected Province and Municipality/City.</div>
    </div>
    <form method="GET" action="{{ url()->current() }}" class="admin-location-switcher" data-admin-location-form>
        @foreach($preserve as $key => $value)
            @if(is_array($value))
                @foreach($value as $item)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        @include('partials.admin-location-cascade', ['idPrefix' => 'admin-filter'])
        <button type="submit" class="staff-btn staff-btn-primary">Apply</button>
    </form>
    @include('partials.admin-scholarship-clubs')
</div>
