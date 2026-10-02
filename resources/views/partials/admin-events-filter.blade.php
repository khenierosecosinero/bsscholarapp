@php
    $preserve = request()->except(['location', 'program_type', 'page', 'club', 'search', 'region']);
    $selectedClubId = (int) ($selectedClubId ?? 0);
    $selectedRegion = (string) ($selectedRegion ?? '');
    $scholarshipClubs = $scholarshipClubs ?? collect();
@endphp

<div class="admin-location-banner admin-events-filter">
        <form method="GET" action="{{ route('admin.events') }}" class="admin-location-switcher admin-filter-form admin-events-filter-form" data-admin-location-form>
        @foreach($preserve as $key => $value)
            @if(is_array($value))
                @foreach($value as $item)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        @include('partials.admin-location-cascade', [
            'idPrefix' => 'admin-events',
            'showCategory' => false,
            'showRegion' => true,
            'selectedRegion' => $selectedRegion,
        ])
        <div class="admin-location-cascade-field">
            <label class="admin-location-cascade-label" for="admin-events-club">Scholarship Club</label>
            <select id="admin-events-club" name="club" data-admin-club data-admin-club-sync-location aria-label="Scholarship Club">
                <option value="">All Scholarship Clubs</option>
                @foreach($scholarshipClubs as $club)
                    @php
                        $clubIsland = $club->program ? \App\Support\PhilippineIslandGroup::fromProgram($club->program) : '';
                    @endphp
                    <option
                        value="{{ $club->id }}"
                        data-province="{{ $club->province }}"
                        data-city="{{ $club->city }}"
                        data-program-id="{{ $club->scholarship_program_id }}"
                        data-island="{{ $clubIsland }}"
                        @selected($selectedClubId === (int) $club->id)
                    >
                        {{ $club->name }}{{ $club->addressLine() ? ' — '.$club->addressLine() : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="admin-location-cascade-field admin-events-search-field">
            <label class="admin-location-cascade-label" for="admin-events-search">Search</label>
            <div class="staff-search">
                <span>🔍</span>
                <input id="admin-events-search" type="search" name="search" value="{{ $search ?? '' }}" placeholder="Search events...">
            </div>
        </div>
        <button type="submit" class="staff-btn staff-btn-primary">Apply</button>
    </form>
</div>
