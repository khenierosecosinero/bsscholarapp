@php

    $active = $active ?? 'dashboard';

    $requiredFallback = \App\Services\ScholarService::REQUIRED_HOURS;
    $hs = $hourStats ?? ['approved' => 0, 'pending' => 0, 'required' => $requiredFallback, 'remaining' => $requiredFallback];

    $pct = $hs['required'] > 0 ? min(100, (int) round(($hs['approved'] / $hs['required']) * 100)) : 0;

    $accountPending = auth()->user()?->isPendingApproval() ?? false;

    $restrictedNav = ['events', 'calendar', 'service-hours', 'documents', 'notifications', 'profile'];

    $navItems = [

        'dashboard' => ['label' => 'Dashboard', 'route' => 'user.dashboard', 'icon' => '&#9632;'],

        'events' => ['label' => 'Events', 'route' => 'user.events', 'icon' => '&#9733;'],

        'calendar' => ['label' => 'Calendar', 'route' => 'user.calendar', 'icon' => '&#128197;'],

        'service-hours' => ['label' => 'Service Hours', 'route' => 'user.service-hours', 'icon' => '&#9201;'],

        'documents' => ['label' => 'Documents', 'route' => 'user.documents', 'icon' => '&#128196;'],

        'notifications' => ['label' => 'Notifications', 'route' => 'user.notifications', 'icon' => '&#128276;'],

        'profile' => ['label' => 'Profile & Settings', 'route' => 'user.profile', 'icon' => '&#9881;'],

    ];

    $docOverview = $documentOverview ?? null;

@endphp



<aside class="sidebar" id="sidebar">

    <div class="brand">

        <div class="brand-lockup">
            <div class="logo">
                <img src="{{ asset('images/bssa-logo.png') }}" alt="Batang Surigaonon Scholar's App logo">
            </div>
            <div class="title">Batang Surigaonon<br>Scholar's App</div>
        </div>

        @if(!empty($scholarshipProgram))

            <div class="scholar-program-badge">{{ auth()->user()->locationLabel() }}</div>

        @endif

        @php $sidebarUser = auth()->user(); @endphp
        @if($sidebarUser)
            <div class="sidebar-user-chip">
                <x-user-avatar :user="$sidebarUser" class="sidebar-user-avatar" />
                <div class="sidebar-user-meta">
                    <div class="sidebar-user-name" title="{{ $sidebarUser->full_name }}">{{ $sidebarUser->full_name }}</div>
                    @if($sidebarUser->scholar_id)
                        <div class="sidebar-user-id">{{ $sidebarUser->scholar_id }}</div>
                    @endif
                </div>
            </div>
        @endif

    </div>



    <nav class="nav" aria-label="Main navigation">

        @foreach($navItems as $key => $item)

            @php $isRestricted = $accountPending && in_array($key, $restrictedNav, true); @endphp

            @if($isRestricted)

                <span class="nav-item is-disabled" title="Unavailable until your account is approved">

                    <span class="nav-icon">{!! $item['icon'] !!}</span>

                    <span class="nav-label">{{ $item['label'] }}</span>

                </span>

            @else

                <a href="{{ route($item['route']) }}" class="nav-item {{ $active === $key ? 'active' : '' }}" @if($key === 'notifications') data-nav="notifications" @endif>

                    <span class="nav-icon">{!! $item['icon'] !!}</span>

                    <span class="nav-label">{{ $item['label'] }}</span>

                    @if($key === 'notifications')
                        @php $unreadCount = (int) ($unreadNotificationsCount ?? $notifStats['unread'] ?? 0); @endphp
                        @if($unreadCount > 0)
                            <span class="nav-notif-badge" data-unread-notifications aria-label="{{ $unreadCount }} unread notifications">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        @endif
                    @endif

                </a>

            @endif



            @if($key === 'profile' && $active === 'notifications' && isset($notifStats))

                @include('partials.notifications-overview-sidebar')

            @endif

        @endforeach

    </nav>



    @if($active === 'dashboard' && !$accountPending)

        @include('partials.dashboard-sidebar-section')

    @endif



    <div class="sidebar-widgets">

        @if($active === 'documents' && $docOverview)

            @php $o = $docOverview; @endphp

            <div class="card documents-overview-sidebar-card">

                <div class="card-header">DOCUMENTS OVERVIEW</div>

                <div class="progress-wrap compact">

                    <div class="progress-circle">

                        <svg viewBox="0 0 36 36">

                            <path class="bg" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>

                            <path class="meter" stroke-dasharray="{{ $o['completion_pct'] }},100" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>

                        </svg>

                        <div class="progress-text">{{ $o['approved'] }} / {{ $o['total'] }}<br><small>Approved</small></div>

                    </div>

                    <div class="hours-list">

                        <div><span class="dot completed"></span> Approved <strong>{{ $o['approved'] }}</strong></div>

                        <div><span class="dot pending-dot"></span> Pending <strong>{{ $o['pending'] }}</strong></div>

                        <div><span class="dot rejected-dot"></span> Rejected <strong>{{ $o['rejected'] }}</strong></div>

                        <div><span class="dot not-submitted-dot"></span> Not Submitted <strong>{{ $o['not_submitted'] }}</strong></div>

                    </div>

                </div>

            </div>



            @include('partials.current-semester-card', ['useEnrollmentStatus' => true])

        @elseif($active === 'profile')

            @include('partials.profile-my-progress-sidebar')

            @include('partials.sidebar-mini-calendar-card', ['calRoute' => 'user.calendar'])

        @else

            @if($active !== 'dashboard' && $active !== 'profile' && $active !== 'notifications' && !$accountPending)

                @include('partials.service-hours-overview-sidebar')

            @endif

            @if($active === 'events')

                @include('partials.upcoming-event-sidebar', ['upcomingEvent' => $upcomingEvent ?? null])

            @endif

            @if($active === 'dashboard' && !$accountPending)

                @include('partials.quick-links-card')

            @endif

            @if($active === 'service-hours')

                @include('partials.current-semester-card')

            @endif

        @endif



        <form method="POST" action="{{ route('logout') }}" class="logout-form">

            @csrf

            <button type="submit" class="btn outline full logout-btn">Logout</button>

        </form>

    </div>

</aside>

