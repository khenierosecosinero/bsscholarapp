@php
    $preserve = request()->except(['location', 'program_type', 'page', 'club', 'search']);
    $selectedClubId = (int) ($selectedClubId ?? 0);
    $scholarshipClubs = $scholarshipClubs ?? collect();
@endphp

<div class="admin-location-banner admin-scholars-filter">
    <form method="GET" action="{{ route('admin.scholars') }}" class="admin-location-switcher admin-scholars-filter-form" data-admin-location-form>
        @foreach($preserve as $key => $value)
            @if(is_array($value))
                @foreach($value as $item)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        @include('partials.admin-location-cascade', ['idPrefix' => 'admin-scholars', 'showCategory' => false])
        <div class="admin-location-cascade-field">
            <label class="admin-location-cascade-label" for="admin-scholars-club">Scholarship Club</label>
            <select id="admin-scholars-club" name="club" aria-label="Scholarship Club" onchange="this.form.submit()">
                <option value="">All Scholarship Clubs</option>
                @foreach($scholarshipClubs as $club)
                    <option value="{{ $club->id }}" @selected($selectedClubId === (int) $club->id)>
                        {{ $club->name }}{{ $club->addressLine() ? ' — '.$club->addressLine() : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="admin-location-cascade-field admin-scholars-search-field">
            <label class="admin-location-cascade-label" for="admin-scholars-search">Search</label>
            <div class="staff-search">
                <span>🔍</span>
                <input id="admin-scholars-search" type="search" name="search" value="{{ $search ?? '' }}" placeholder="Search by name, scholar ID, or email...">
            </div>
        </div>
        <button type="submit" class="staff-btn staff-btn-primary">Apply</button>
    </form>
</div>
