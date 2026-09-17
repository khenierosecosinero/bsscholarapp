@php
    $userName = $user->full_name ?? 'Scholar';
    $userRole = $user->scholarshipProgram?->location_name ?? 'Scholar';
@endphp

<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger" id="sidebar-toggle" type="button" aria-label="Toggle menu" aria-expanded="true">&#9776;</button>
        <h1>{{ $pageTitle }}</h1>
    </div>
    <div class="profile-card" id="profile-dropdown-wrap">
        <x-user-avatar :user="$user ?? auth()->user()" class="avatar" />
        <div class="profile-info">
            <div class="name" title="{{ $userName }}">{{ $userName }}</div>
            <div class="role">{{ $userRole }}</div>
        </div>
        <button type="button" class="profile-toggle" id="profile-toggle" aria-label="Profile menu for {{ $userName }}" aria-haspopup="true">&#9662;</button>
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
    @if(!empty($pageSubtitle))
        <p class="muted topbar-subtitle">{{ $pageSubtitle }}</p>
    @endif
</header>
