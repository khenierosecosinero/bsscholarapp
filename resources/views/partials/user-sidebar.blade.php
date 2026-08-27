@php

    $active = $active ?? 'dashboard';

    $hs = $hourStats ?? ['approved' => 0, 'pending' => 0, 'required' => 30, 'remaining' => 30];

    $pct = $hs['required'] > 0 ? round(($hs['approved'] / $hs['required']) * 100) : 0;

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

    $docSubmitted = isset($documents) ? $documents->whereIn('status', ['submitted', 'pending'])->count() : null;

    $docTotal = isset($documentTypes) ? $documentTypes->count() : 5;

@endphp



<aside class="sidebar" id="sidebar">

    <div class="brand">

        <div class="logo">

            <img src="https://ui-avatars.com/api/?name=BS&background=2fa76a&color=fff&size=56" alt="Batang Surigaonon logo">

        </div>

        <div class="title">Batang Surigaonon<br>Scholar's App</div>

        @if(!empty($scholarshipProgram))

            <div class="scholar-program-badge">{{ auth()->user()->locationLabel() }}</div>

        @endif

    </div>



    <nav class="nav" aria-label="Main navigation">

        @foreach($navItems as $key => $item)

            @php $isRestricted = $accountPending && in_array($key, $restrictedNav, true); @endphp

            @if($isRestricted)

                <span class="nav-item is-disabled" title="Unavailable until your account is approved">

                    <span class="nav-icon">{!! $item['icon'] !!}</span>

                    {{ $item['label'] }}

                </span>

            @else

                <a href="{{ route($item['route']) }}" class="nav-item {{ $active === $key ? 'active' : '' }}">

                    <span class="nav-icon">{!! $item['icon'] !!}</span>

                    {{ $item['label'] }}

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

        @if($active === 'documents' && isset($documents))

            @php

                $submitted = $documents->whereIn('status', ['submitted', 'pending'])->count();

                $pending = $documents->where('status', 'pending')->count();

                $notSubmitted = $documents->where('status', 'not_submitted')->count();

                $docPct = $docTotal > 0 ? round(($submitted / $docTotal) * 100) : 0;

            @endphp

            <div class="card documents-overview-sidebar-card">

                <div class="card-header">DOCUMENTS OVERVIEW</div>

                <div class="progress-wrap compact">

                    <div class="progress-circle">

                        <svg viewBox="0 0 36 36">

                            <path class="bg" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>

                            <path class="meter" stroke-dasharray="{{ $docPct }},100" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>

                        </svg>

                        <div class="progress-text">{{ $submitted }} / {{ $docTotal }}<br><small>Submitted</small></div>

                    </div>

                    <div class="hours-list">

                        <div><span class="dot completed"></span> Submitted <strong>{{ $submitted }}</strong></div>

                        <div><span class="dot pending-dot"></span> Pending <strong>{{ $pending }}</strong></div>

                        <div><span class="dot rejected-dot"></span> Not Submitted <strong>{{ $notSubmitted }}</strong></div>

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

