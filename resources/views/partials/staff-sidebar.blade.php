@php
    $active = $active ?? 'dashboard';
    $programName = $program->location_name ?? 'Scholarship Program';
    $navItems = [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'icon' => '&#9632;'],
        'scholars' => ['label' => 'Manage Scholars', 'route' => 'staff.scholars', 'icon' => '&#128101;'],
    ];
@endphp

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="logo">
            <img src="https://ui-avatars.com/api/?name=BS&background=2563eb&color=fff&size=56" alt="BSSA Staff logo">
        </div>
        <div class="title">City's Scholar<br>Staff Portal</div>
        <div class="staff-program-badge">{{ $programName }}</div>
    </div>

    <nav class="nav" aria-label="Staff navigation">
        @foreach($navItems as $key => $item)
            <a href="{{ route($item['route']) }}" class="nav-item {{ $active === $key ? 'active' : '' }}">
                <span class="nav-icon">{!! $item['icon'] !!}</span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</aside>
