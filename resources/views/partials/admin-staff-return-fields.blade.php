<input type="hidden" name="location" value="{{ $locationKey ?? session('admin_location', 'all') }}">
<input type="hidden" name="club" value="{{ $selectedClubId ?? '' }}">
<input type="hidden" name="region" value="{{ $selectedRegion ?? '' }}">
<input type="hidden" name="search" value="{{ $search ?? '' }}">
