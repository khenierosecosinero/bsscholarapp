@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/user-nav.css') }}?v={{ filemtime(public_path('css/user-nav.css')) }}">
    @if(Auth::check() && Auth::user()->isPendingApproval())
        <link rel="stylesheet" href="{{ asset('css/pending-approval-modal.css') }}">
    @endif
@endpush

@section('content')
<div class="app-shell" id="app-shell">
    <button type="button" class="nav-overlay" id="nav-overlay" hidden aria-label="Close menu"></button>
    @include('partials.user-sidebar', ['active' => $active ?? 'dashboard'])
    <main class="main">
        <div class="inner">
            @include('partials.user-topbar', [
                'user' => $user ?? Auth::user(),
                'pageTitle' => $pageTitle ?? 'Dashboard',
                'pageSubtitle' => $pageSubtitle ?? '',
            ])
            @include('partials.flash-messages')
            @if(auth()->user()?->hasScholarPortalAccess())
                <div
                    hidden
                    id="attendance-live-root"
                    data-attendance-live="{{ route('user.attendance.status') }}"
                    data-events-url="{{ route('user.events') }}"
                    data-attendance-page="{{ $active ?? '' }}"
                    data-attendance-event="{{ $selected['id'] ?? '' }}"
                    data-attendance-status="{{ $selected['attendance_status'] ?? '' }}"
                    data-latest-notification="0"
                ></div>
            @endif
            @yield('page-content')
        </div>
    </main>
</div>
@include('partials.pending-approval-modal', ['showPendingApprovalModal' => $showPendingApprovalModal ?? false])
@endsection

@section('scripts')
    @vite(['resources/js/user-app.js'])
    @stack('scripts')
@endsection
