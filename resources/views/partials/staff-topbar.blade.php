@php
    $staffName = $staff->full_name ?? 'Scholar Staff';
    $programLabel = $program->name ?? 'Scholarship Program Management';
@endphp

<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger" id="sidebar-toggle" type="button" aria-label="Toggle menu" aria-expanded="true">&#9776;</button>
        <div>
            <h1>{{ $pageTitle }}</h1>
            @if(!empty($pageSubtitle))
                <p class="muted">{{ $pageSubtitle }}</p>
            @else
                <p class="muted">{{ $programLabel }}</p>
            @endif
        </div>
    </div>
    <div class="profile-card" id="profile-dropdown-wrap">
        <div class="avatar staff-avatar">{{ strtoupper(substr($staffName, 0, 1)) }}</div>
        <div class="profile-info">
            <div class="name">{{ $staffName }}</div>
            <div class="role">Scholar Staff</div>
        </div>
        <button type="button" class="profile-toggle" id="profile-toggle" aria-label="Profile menu">&#9662;</button>
        <div class="profile-menu" id="profile-menu" hidden>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </div>
    </div>
</header>
