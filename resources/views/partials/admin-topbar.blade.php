@php
    $adminName = $admin->full_name ?? 'Administrator';
    $locationKey = $locationKey ?? session('admin_location', 'all');
@endphp

<header class="staff-topbar">
    <div class="staff-topbar-left">
        <button class="staff-hamburger" id="sidebar-toggle" type="button" aria-label="Toggle menu">&#9776;</button>
        <div class="staff-topbar-titles">
            <h1>{{ $pageTitle }}</h1>
            @if(!empty($pageSubtitle))
                <p>{{ $pageSubtitle }}</p>
            @endif
        </div>
    </div>
    <div class="staff-topbar-right">
        <div class="staff-breadcrumb">Admin / {{ $breadcrumb ?? $pageTitle }}</div>
        <div class="staff-topbar-actions">
            <span class="admin-location-pill">📍 {{ $locationLabel ?? 'Overall / All Locations' }}</span>
            <div class="staff-profile-chip">
                <div class="staff-profile-avatar">{{ strtoupper(substr($adminName, 0, 1)) }}</div>
                <span>{{ $adminName }}</span>
                <span class="staff-badge admin-badge-admin">Admin</span>
            </div>
        </div>
    </div>
</header>
