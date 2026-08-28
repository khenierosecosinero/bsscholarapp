@php
    $active = $active ?? 'dashboard';
    $adminName = $admin->full_name ?? 'Administrator';
    $navLocation = (string) ($locationKey ?? session('admin_location', 'all'));
    $navQuery = $navLocation !== 'all' ? ['location' => $navLocation] : [];

    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home'],
        ['key' => 'locations', 'label' => 'Locations', 'route' => 'admin.locations', 'icon' => 'locations'],
        ['key' => 'scholars', 'label' => 'Scholars', 'route' => 'admin.scholars', 'icon' => 'users'],
        ['key' => 'staff', 'label' => 'Scholar Staff', 'route' => 'admin.staff', 'icon' => 'users'],
        ['key' => 'events', 'label' => 'Events', 'route' => 'admin.events', 'icon' => 'calendar'],
        ['key' => 'attendance', 'label' => 'Attendance', 'route' => 'admin.attendance', 'icon' => 'clock'],
        ['key' => 'service-hours', 'label' => 'Service Hours', 'route' => 'admin.service-hours', 'icon' => 'clock'],
        ['key' => 'documents', 'label' => 'Documents', 'route' => 'admin.documents', 'icon' => 'file'],
        ['key' => 'participation', 'label' => 'Participation', 'route' => 'admin.participation', 'icon' => 'participation'],
        ['key' => 'reports', 'label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'report'],
        ['key' => 'settings', 'label' => 'Admin Settings', 'route' => 'admin.settings', 'icon' => 'settings'],
    ];
@endphp

<aside class="staff-sidebar" id="sidebar">
    <div class="staff-brand">
        <div class="staff-brand-logo">
            <img src="https://ui-avatars.com/api/?name=AD&background=c2410c&color=fff&size=56" alt="BSSA Admin">
        </div>
        <div class="staff-brand-text">Batang Surigaonon<br>Admin Portal</div>
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
                @endphp
                <a href="{{ route($item['route'], $navQuery) }}" class="staff-nav-item {{ $active === $item['key'] ? 'active' : '' }}">
                    <span class="staff-nav-icon {{ $iconClass }}"></span>
                    <span class="staff-nav-label">{{ $item['label'] }}</span>
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
