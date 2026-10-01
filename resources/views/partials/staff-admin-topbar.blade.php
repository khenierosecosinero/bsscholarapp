@php
    $staffName = $staff->full_name ?? 'Scholar Staff';
    $staffRole = $staff->scholarshipClubName() ?? 'Scholar Staff';
    $staffAddress = $staff->scholarshipClubAddress();
@endphp

<header class="staff-topbar">
    <div class="staff-topbar-left">
        <button class="staff-hamburger" id="sidebar-toggle" type="button" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
        <h1 class="staff-topbar-title">{{ $pageTitle }}</h1>
    </div>
    <div class="staff-topbar-right">
        <div class="staff-profile-card" id="profile-dropdown-wrap">
            <div class="staff-profile-avatar">{{ strtoupper(substr($staffName, 0, 1)) }}</div>
            <div class="staff-profile-info">
                <div class="name">{{ $staffName }}</div>
                <div class="role">{{ $staffRole }}{{ $staffAddress ? ' — '.$staffAddress : '' }}</div>
            </div>
            <button type="button" class="staff-profile-toggle" id="profile-toggle" aria-label="Profile menu">&#9662;</button>
            <div class="profile-menu" id="profile-menu" hidden>
                @include('partials.logout-button', ['class' => 'app-logout-btn'])
            </div>
        </div>
    </div>
    @if(!empty($pageSubtitle))
        <p class="staff-topbar-subtitle">{{ $pageSubtitle }}</p>
    @endif
</header>
