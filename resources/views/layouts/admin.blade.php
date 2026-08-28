@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/staff-admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('content')
<div class="staff-shell admin-shell" id="app-shell">
    @include('partials.admin-sidebar')
    <div class="staff-main-wrap">
        @include('partials.admin-topbar')
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

        var locationSelect = document.getElementById('admin-location-select');
        if (locationSelect) {
            locationSelect.addEventListener('change', function () {
                this.form.submit();
            });
        }
    });
    </script>
@endsection
