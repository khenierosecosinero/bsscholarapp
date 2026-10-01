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
        var bindAdminLocationForm = function (form) {
            var treeEl = form.querySelector('[data-admin-location-tree]');
            var typeSelect = form.querySelector('[data-admin-program-type]');
            var provinceSelect = form.querySelector('[data-admin-province]');
            var citySelect = form.querySelector('[data-admin-city]');
            var locationInput = form.querySelector('[data-admin-location-value]');
            var selectedCityEl = form.querySelector('[data-admin-selected-city]');
            if (!treeEl || !provinceSelect || !citySelect || !locationInput) {
                return;
            }

            var tree = [];
            try {
                tree = JSON.parse(treeEl.textContent || '[]');
            } catch (error) {
                tree = [];
            }

            var selectedCity = selectedCityEl ? selectedCityEl.value : '';

            var provinceNode = function () {
                return tree.find(function (province) {
                    return province.name === provinceSelect.value;
                }) || null;
            };

            var fillCities = function (keepCity) {
                var isCityScholar = !typeSelect || typeSelect.value === 'city_municipality';
                citySelect.disabled = !isCityScholar;
                citySelect.innerHTML = '';
                var empty = document.createElement('option');
                empty.value = '';
                empty.textContent = isCityScholar ? 'Select municipality or city' : 'Not used for Province Scholar';
                citySelect.appendChild(empty);

                if (!isCityScholar) {
                    return;
                }

                var node = provinceNode();
                (node && node.cities ? node.cities : []).forEach(function (city) {
                    var option = document.createElement('option');
                    option.value = city.name;
                    option.setAttribute('data-id', city.id || '');
                    option.textContent = city.name;
                    if (keepCity && city.name === selectedCity) {
                        option.selected = true;
                    }
                    citySelect.appendChild(option);
                });
            };

            var syncLocation = function () {
                var node = provinceNode();
                if (!node) {
                    locationInput.value = 'all';
                    return;
                }

                if ((typeSelect && typeSelect.value === 'province') || !citySelect.value) {
                    locationInput.value = node.id ? String(node.id) : 'all';
                    return;
                }

                var city = (node.cities || []).find(function (item) {
                    return item.name === citySelect.value;
                });
                locationInput.value = city && city.id ? String(city.id) : (node.id ? String(node.id) : 'all');
            };

            fillCities(true);
            syncLocation();

            if (typeSelect) {
                typeSelect.addEventListener('change', function () {
                    selectedCity = '';
                    fillCities(false);
                    syncLocation();
                    form.submit();
                });
            }
            provinceSelect.addEventListener('change', function () {
                selectedCity = '';
                fillCities(false);
                syncLocation();
                form.submit();
            });
            citySelect.addEventListener('change', function () {
                selectedCity = citySelect.value;
                syncLocation();
                form.submit();
            });
        };

        document.querySelectorAll('[data-admin-location-form]').forEach(bindAdminLocationForm);

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
