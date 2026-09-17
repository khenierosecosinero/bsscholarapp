@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/staff-admin.css') }}?v={{ filemtime(public_path('css/staff-admin.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
@endpush

@section('content')
<div class="staff-shell admin-shell" id="app-shell">
    <button type="button" class="nav-overlay" id="nav-overlay" hidden aria-label="Close menu"></button>
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
        var locationSelect = document.getElementById('admin-location-select');
        if (locationSelect) {
            locationSelect.addEventListener('change', function () {
                this.form.submit();
            });
        }

        var dashboardSelect = document.getElementById('admin-dashboard-location-select');
        var dashboardForm = document.getElementById('admin-dashboard-scope-form');
        var dashboardType = document.getElementById('dashboard-program-type');
        if (dashboardSelect && dashboardForm) {
            dashboardSelect.addEventListener('change', function () {
                var option = this.options[this.selectedIndex];
                if (dashboardType) {
                    dashboardType.value = option.getAttribute('data-program-type') || 'all';
                }
                dashboardForm.submit();
            });
        }

        var staffNav = document.querySelector('[data-admin-nav="staff"]');
        if (staffNav) {
            var badgesUrl = @json(route('admin.sidebar-badges'));
            var updateStaffBadge = function (count) {
                count = parseInt(count, 10) || 0;
                var badge = staffNav.querySelector('[data-admin-staff-badge]');
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'staff-nav-badge';
                        badge.setAttribute('data-admin-staff-badge', '');
                        staffNav.appendChild(badge);
                    }
                    badge.textContent = count > 99 ? '99+' : String(count);
                    badge.setAttribute('aria-label', count + ' pending staff registrations');
                } else if (badge) {
                    badge.remove();
                }
            };
            var pollStaffBadge = function () {
                if (document.hidden || staffBadgeInFlight) return;
                staffBadgeInFlight = true;
                fetch(badgesUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) return null;
                    return response.json();
                }).then(function (data) {
                    if (data && typeof data.staff !== 'undefined') {
                        updateStaffBadge(data.staff);
                    }
                }).catch(function () {}).finally(function () {
                    staffBadgeInFlight = false;
                });
            };
            var staffBadgeInFlight = false;
            setInterval(pollStaffBadge, 20000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) pollStaffBadge();
            });
        }
    });
    </script>
    @stack('scripts')
@endsection
