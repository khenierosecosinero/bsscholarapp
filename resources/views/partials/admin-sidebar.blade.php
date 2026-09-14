@php
    $active = $active ?? 'dashboard';
    $adminName = $admin->full_name ?? 'Administrator';
    $navLocation = (string) ($locationKey ?? session('admin_location', 'all'));
    $navProgramType = (string) ($programType ?? session('admin_program_type', 'all'));
    $navQuery = array_filter([
        'location' => $navLocation !== 'all' ? $navLocation : null,
        'program_type' => $navProgramType !== 'all' ? $navProgramType : null,
    ]);

    $badges = $adminSidebarBadges ?? [];

    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home', 'badgeLabel' => 'items requiring attention'],
        ['key' => 'locations', 'label' => 'Locations', 'route' => 'admin.locations', 'icon' => 'locations', 'badgeLabel' => 'locations needing attention'],
        ['key' => 'scholars', 'label' => 'Scholars', 'route' => 'admin.scholars', 'icon' => 'users', 'badgeLabel' => 'pending scholar registrations'],
        ['key' => 'staff', 'label' => 'Scholar Staff', 'route' => 'admin.staff', 'icon' => 'users', 'badgeLabel' => 'pending staff registrations'],
        ['key' => 'events', 'label' => 'Events', 'route' => 'admin.events', 'icon' => 'calendar', 'badgeLabel' => 'pending events'],
        ['key' => 'attendance', 'label' => 'Attendance', 'route' => 'admin.attendance', 'icon' => 'clock', 'badgeLabel' => 'pending attendance records'],
        ['key' => 'service-hours', 'label' => 'Service Hours', 'route' => 'admin.service-hours', 'icon' => 'clock', 'badgeLabel' => 'scholars with pending service hours'],
        ['key' => 'documents', 'label' => 'Documents', 'route' => 'admin.documents', 'icon' => 'file', 'badgeLabel' => 'pending document submissions'],
        ['key' => 'participation', 'label' => 'Participation', 'route' => 'admin.participation', 'icon' => 'participation', 'badgeLabel' => 'pending or failed participation records'],
        ['key' => 'reports', 'label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'report', 'badgeLabel' => 'scholars needing completion review'],
        ['key' => 'settings', 'label' => 'Admin Settings', 'route' => 'admin.settings', 'icon' => 'settings', 'badgeLabel' => 'settings items requiring attention'],
    ];
@endphp

<aside class="staff-sidebar" id="sidebar">
    <div class="staff-brand">
        <div class="staff-brand-lockup">
            <div class="staff-brand-logo">
                <img src="{{ asset('images/bssa-logo.png') }}" alt="Batang Surigaonon Scholar's App logo">
            </div>
            <div class="staff-brand-text">Batang Surigaonon<br>Admin Portal</div>
        </div>
    </div>

    <nav class="staff-nav" aria-label="Admin navigation">
        <div class="staff-nav-section">
            <div class="staff-nav-heading">ADMINISTRATION</div>
            @foreach($navItems as $item)
                @php
                    $iconClass = match ($item['icon']) {
                        'locations' => 'admin-icon-locations',
                        'participation' => 'admin-icon-participation',
                        default => 'staff-icon-'.$item['icon'],
                    };
                    $badgeCount = (int) ($badges[$item['key']] ?? 0);
                @endphp
                <a href="{{ route($item['route'], $navQuery) }}" class="staff-nav-item {{ $active === $item['key'] ? 'active' : '' }}" @if($item['key'] === 'staff') data-admin-nav="staff" @endif>
                    <span class="staff-nav-icon {{ $iconClass }}"></span>
                    <span class="staff-nav-label">{{ $item['label'] }}</span>
                    @if($item['key'] === 'staff')
                        @if($badgeCount > 0)
                            <span class="staff-nav-badge" data-admin-staff-badge aria-label="{{ $badgeCount }} {{ $item['badgeLabel'] }}">{{ $badgeCount > 99 ? '99+' : $badgeCount }}</span>
                        @endif
                    @elseif($badgeCount > 0)
                        <span class="staff-notif-badge" aria-label="{{ $badgeCount }} {{ $item['badgeLabel'] }}">{{ $badgeCount > 99 ? '99+' : $badgeCount }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>

    <div class="staff-sidebar-footer">
        <div class="staff-user-chip">
            <div class="staff-user-avatar">{{ strtoupper(substr($adminName, 0, 1)) }}</div>
            <div class="staff-user-meta">
                <div class="staff-user-name">{{ $adminName }}</div>
                <div class="staff-user-role">System Administrator</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="staff-logout-form">
            @csrf
            <button type="submit" class="staff-logout-btn">Logout</button>
        </form>
    </div>
</aside>
