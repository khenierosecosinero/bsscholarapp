@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/staff-admin.css') }}?v={{ filemtime(public_path('css/staff-admin.css')) }}">
@endpush

@section('content')
<div class="staff-shell" id="app-shell">
    <button type="button" class="nav-overlay" id="nav-overlay" hidden aria-label="Close menu"></button>
    @include('partials.staff-admin-sidebar')
    <div class="staff-main-wrap">
        @include('partials.staff-admin-topbar')
        <main class="staff-main">
            @include('partials.flash-messages')
            @include('partials.staff-confirm-modal')
            @yield('page-content')
        </main>
    </div>
</div>
@endsection

@section('scripts')
    @vite(['resources/js/user-app.js'])
    @stack('scripts')
@endsection
