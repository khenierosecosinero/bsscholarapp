@php
    $active = $active ?? 'dashboard';
    $pendingApprovalsCount = $pendingApprovalsCount ?? 0;
    $programName = $staff->locationLabel();

    $sections = [
        'Dashboard' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'staff.dashboard', 'icon' => 'home'],
        ],
        'Management' => [
            ['key' => 'scholars', 'label' => 'Scholars', 'route' => 'staff.scholars', 'icon' => 'users'],
            ['key' => 'events', 'label' => 'Events', 'route' => 'staff.events', 'icon' => 'calendar'],
            ['key' => 'attendance', 'label' => 'Attendance', 'route' => 'staff.attendance', 'icon' => 'clock'],
            ['key' => 'documents', 'label' => 'Documents', 'route' => 'staff.documents', 'icon' => 'file'],
            ['key' => 'approval-requests', 'label' => 'Approval Requests', 'route' => 'staff.approval-requests', 'icon' => 'check', 'badge' => $pendingApprovalsCount],
        ],
        'Reports' => [
            ['key' => 'service-hours-reports', 'label' => 'Service Hours Reports', 'route' => 'staff.reports.service-hours', 'icon' => 'report'],
            ['key' => 'attendance-reports', 'label' => 'Attendance Reports', 'route' => 'staff.reports.attendance', 'icon' => 'report'],
            ['key' => 'participation-reports', 'label' => 'Participation Reports', 'route' => 'staff.reports.participation', 'icon' => 'report'],
            ['key' => 'completion-reports', 'label' => 'Completion Reports', 'route' => 'staff.reports.completion', 'icon' => 'report'],
        ],
        'System' => [
            ['key' => 'calendar', 'label' => 'Calendar', 'route' => 'staff.calendar', 'icon' => 'calendar'],
            ['key' => 'settings', 'label' => 'Settings', 'route' => 'staff.settings', 'icon' => 'settings'],
        ],
    ];
@endphp

<aside class="staff-sidebar" id="sidebar">
    <div class="staff-brand">
        <div class="staff-brand-logo">
            <img src="https://ui-avatars.com/api/?name=BS&background=2563eb&color=fff&size=56" alt="BSSA logo">
        </div>
        <div class="staff-brand-text">Batang Surigaonon<br>Scholar's App</div>
    </div>

    <nav class="staff-nav" aria-label="Staff navigation">
        @foreach($sections as $section => $items)
            <div class="staff-nav-section">
                <div class="staff-nav-heading">{{ strtoupper($section) }}</div>
                @foreach($items as $item)
                    <a href="{{ route($item['route']) }}" class="staff-nav-item {{ $active === $item['key'] ? 'active' : '' }}">
                        <span class="staff-nav-icon staff-icon-{{ $item['icon'] }}"></span>
                        <span class="staff-nav-label">{{ $item['label'] }}</span>
                        @if(!empty($item['badge']) && $item['badge'] > 0)
                            <span class="staff-nav-badge">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="staff-sidebar-footer">
        <div class="staff-user-chip">
            <div class="staff-user-avatar">{{ strtoupper(substr($staff->full_name ?? 'S', 0, 1)) }}</div>
            <div class="staff-user-meta">
                <div class="staff-user-name">{{ $staff->full_name ?? 'Scholar Staff' }}</div>
                <div class="staff-user-role">{{ $programName }}</div>
            </div>
            <span class="staff-user-chevron">&#9662;</span>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="staff-logout-form">
            @csrf
            <button type="submit" class="staff-logout-btn">Logout</button>
        </form>
    </div>
</aside>
