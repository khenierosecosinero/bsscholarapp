@php
    $staffName = $staff->full_name ?? 'Scholar Staff';
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
        <div class="staff-breadcrumb">Dashboard / {{ $breadcrumb ?? $pageTitle }}</div>
        <div class="staff-topbar-actions">
            <button type="button" class="staff-icon-btn" aria-label="Notifications">
                &#128276;
                @if(($pendingApprovalsCount ?? 0) > 0)
                    <span class="staff-notif-badge">{{ $pendingApprovalsCount }}</span>
                @endif
            </button>
            <div class="staff-profile-chip">
                <div class="staff-profile-avatar">{{ strtoupper(substr($staffName, 0, 1)) }}</div>
                <span>{{ $staffName }}</span>
                <span class="staff-user-chevron">&#9662;</span>
            </div>
        </div>
    </div>
</header>
