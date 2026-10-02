@php
    $locationKey = $locationKey ?? session('admin_location', 'all');
@endphp

<div class="staff-card admin-dashboard-scope-card">
    <div class="staff-card-header">
        <h2>Location</h2>
    </div>
    <p class="staff-muted admin-dashboard-scope-copy">
        Choose a Province, then a Municipality/City. Dashboard statistics include only records registered in the selected location.
    </p>
    <form method="GET" action="{{ route('admin.dashboard') }}" class="admin-dashboard-scope-form admin-filter-form" id="admin-dashboard-scope-form" data-admin-location-form>
        @include('partials.admin-location-cascade', ['idPrefix' => 'admin-dashboard', 'showCategory' => false])
    </form>
</div>
