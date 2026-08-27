@php
    $userName = $user->full_name ?? 'Scholar';
@endphp

<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger" id="sidebar-toggle" type="button" aria-label="Toggle menu" aria-expanded="true">&#9776;</button>
        <div>
            <h1>{{ $pageTitle }}</h1>
            @if(!empty($pageSubtitle))
                <p class="muted">{{ $pageSubtitle }}</p>
            @endif
        </div>
    </div>
    <div class="profile-card" id="profile-dropdown-wrap">
        <div class="avatar">{{ strtoupper(substr($userName, 0, 1)) }}</div>
        <div class="profile-info">
            <div class="name">{{ $userName }}</div>
            <div class="role">{{ $user->scholarshipProgram?->location_name ?? 'Scholar' }}</div>
        </div>
        <button type="button" class="profile-toggle" id="profile-toggle" aria-label="Profile menu">&#9662;</button>
        <div class="profile-menu" id="profile-menu" hidden>
            @if(auth()->user()?->hasScholarPortalAccess())
                <a href="{{ route('user.profile') }}">Profile & Settings</a>
            @else
                <span class="is-disabled">Profile & Settings</span>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </div>
    </div>
</header>
