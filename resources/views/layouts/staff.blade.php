@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/staff-admin.css') }}">
@endpush

@section('content')
<div class="staff-shell" id="app-shell">
    @include('partials.staff-admin-sidebar')
    <div class="staff-main-wrap">
        @include('partials.staff-admin-topbar')
        <main class="staff-main">
            @include('partials.flash-messages')
            @yield('page-content')
        </main>
    </div>
</div>
@endsection

@section('scripts')
    @vite(['resources/js/user-app.js'])
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('sidebar-toggle');
        var shell = document.getElementById('app-shell');
        if (!toggle || !shell) return;
        toggle.addEventListener('click', function () {
            shell.classList.toggle('sidebar-open');
        });
    });
    </script>
@endsection
