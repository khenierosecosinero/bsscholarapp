@php
    $fieldId = $fieldId ?? 'scholarship_program_id';
    $selectedId = old('scholarship_program_id', $selectedId ?? '');
    $selectedClubId = old('scholarship_club_id', $selectedClubId ?? '');
    $tree = $locationTree ?? [];
    $requireCity = $requireCity ?? true;
    $addressMode = $addressMode ?? false;
    $showClubSelect = $showClubSelect ?? false;
    $submitProgramId = $submitProgramId ?? ! $showClubSelect;
    $selectedProvinceId = '';
    $selectedProvinceName = '';
    $selectedCityLabel = '';

    foreach ($tree as $province) {
        if ((string) ($province['id'] ?? '') === (string) $selectedId) {
            $selectedProvinceId = (string) $province['id'];
            $selectedProvinceName = $province['name'];
            break;
        }

        foreach ($province['cities'] ?? [] as $city) {
            if ((string) $city['id'] === (string) $selectedId) {
                $selectedProvinceId = (string) ($province['id'] ?? $province['name']);
                $selectedProvinceName = $province['name'];
                $selectedCityLabel = $city['name'];
                break 2;
            }

            foreach ($city['clubs'] ?? [] as $club) {
                if ((string) ($club['id'] ?? '') === (string) $selectedClubId) {
                    $selectedProvinceId = (string) ($province['id'] ?? $province['name']);
                    $selectedProvinceName = $province['name'];
                    $selectedCityLabel = $city['name'];
                    $selectedId = (string) $city['id'];
                    break 3;
                }
            }
        }

        foreach ($province['clubs'] ?? [] as $club) {
            if ((string) ($club['id'] ?? '') === (string) $selectedClubId) {
                $selectedProvinceId = (string) ($province['id'] ?? $province['name']);
                $selectedProvinceName = $province['name'];
                $selectedId = (string) ($province['id'] ?? '');
                break 2;
            }
        }
    }
@endphp

@once
<style>
    .auth-page .location-cascade {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin: 0 0 12px;
    }

    .auth-page .location-cascade-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        margin: 0 0 6px;
        padding-left: 4px;
    }

    .auth-page .location-cascade select.form-input {
        margin: 0;
        width: 100%;
        box-sizing: border-box;
    }

    .auth-page .location-cascade select:disabled {
        color: #9ca3af;
        cursor: not-allowed;
    }

    .staff-settings-grid .location-cascade input[type="hidden"] {
        display: none;
    }
</style>
@endonce

<div class="location-cascade" data-location-cascade data-require-city="{{ $requireCity ? '1' : '0' }}" data-address-mode="{{ $addressMode ? '1' : '0' }}" data-show-club="{{ $showClubSelect ? '1' : '0' }}" data-selected-program="{{ $selectedId }}" data-selected-club="{{ $selectedClubId }}">
    @if($submitProgramId)
        <input
            type="hidden"
            id="{{ $fieldId }}"
            name="scholarship_program_id"
            value="{{ $selectedId }}"
            data-saved-value="{{ $selectedId }}"
            required
        >
    @endif

    <div>
        <label class="location-cascade-label" for="{{ $fieldId }}_province">Province</label>
        <select
            id="{{ $fieldId }}_province"
            class="form-input form-select"
            data-location-province
            data-profile-editable
            data-saved-value="{{ $selectedProvinceId }}"
            required
        >
            <option value="">Select province</option>
            @foreach($tree as $province)
                @php
                    $provinceValue = $province['id'] ?? $province['name'];
                @endphp
                <option
                    value="{{ $provinceValue }}"
                    data-province-id="{{ $province['id'] ?? '' }}"
                    data-province-name="{{ $province['name'] }}"
                    {{ (string) $selectedProvinceId === (string) $provinceValue ? 'selected' : '' }}
                >
                    {{ $province['name'] }}{{ $province['region'] ? ' — '.$province['region'] : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="location-cascade-label" for="{{ $fieldId }}_city">Municipality / City</label>
        <select
            id="{{ $fieldId }}_city"
            class="form-input form-select"
            data-location-city
            data-profile-editable
            data-saved-value="{{ $selectedId }}"
            required
            {{ $selectedProvinceName ? '' : 'disabled' }}
        >
            <option value="">{{ $requireCity ? 'Select municipality or city' : 'Entire province or a municipality/city' }}</option>
        </select>
    </div>

    @if($showClubSelect)
        <div>
            <label class="location-cascade-label" for="{{ $fieldId }}_club">Scholarship Club</label>
            <select
                id="{{ $fieldId }}_club"
                class="form-input form-select"
                name="scholarship_club_id"
                data-location-club
                required
                {{ $selectedProvinceName ? '' : 'disabled' }}
            >
                <option value="">Select scholarship club</option>
            </select>
        </div>
    @endif
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tree = @json($tree);

    document.querySelectorAll('[data-location-cascade]').forEach(function (field) {
        var hiddenInput = field.querySelector('input[type="hidden"]');
        var provinceSelect = field.querySelector('[data-location-province]');
        var citySelect = field.querySelector('[data-location-city]');
        var clubSelect = field.querySelector('[data-location-club]');
        var requireCity = field.getAttribute('data-require-city') === '1';
        var addressMode = field.getAttribute('data-address-mode') === '1';
        var selectedId = String(field.getAttribute('data-selected-program') || (hiddenInput && hiddenInput.value) || '');
        var selectedClubId = String(field.getAttribute('data-selected-club') || '');

        if (!provinceSelect || !citySelect) {
            return;
        }

        function provinceRecord(value) {
            return tree.find(function (province) {
                return String(province.id || province.name) === String(value);
            }) || null;
        }

        function setCityOptions(province, selectedCityId) {
            var placeholder = requireCity
                ? 'Select municipality or city'
                : 'Entire province or a municipality/city';

            citySelect.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = placeholder;
            citySelect.appendChild(empty);

            var editingForm = field.closest('[data-profile-edit-form]');
            var canEdit = !editingForm || editingForm.classList.contains('is-editing');

            if (!province) {
                citySelect.disabled = true;
                return;
            }

            citySelect.disabled = !canEdit;

            if (!requireCity && province.id) {
                var allOption = document.createElement('option');
                allOption.value = String(province.id);
                allOption.textContent = province.name + ' — Province Scholar Program';
                if (selectedCityId && String(selectedCityId) === String(province.id)) {
                    allOption.selected = true;
                }
                citySelect.appendChild(allOption);
            }

            (province.cities || []).forEach(function (city) {
                var option = document.createElement('option');
                option.value = String(city.id);
                option.textContent = addressMode ? city.name : (city.name + ' — City Scholar Program');
                if (selectedCityId && String(selectedCityId) === String(city.id)) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });

            if (!selectedCityId && !requireCity && province.id) {
                citySelect.value = String(province.id);
            }
        }

        function clubsForProvince(province, cityId) {
            var clubs = [];
            var seen = {};

            function addClubs(list) {
                (list || []).forEach(function (club) {
                    var key = String(club.id);
                    if (seen[key]) {
                        return;
                    }
                    seen[key] = true;
                    clubs.push(club);
                });
            }

            if (!province) {
                return clubs;
            }

            addClubs(province.clubs);

            (province.cities || []).forEach(function (city) {
                if (cityId && String(cityId) !== String(province.id) && String(city.id) !== String(cityId)) {
                    return;
                }
                addClubs(city.clubs);
            });

            return clubs;
        }

        function setClubOptions(province, selectedClub) {
            if (!clubSelect) {
                return;
            }

            clubSelect.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'Select scholarship club';
            clubSelect.appendChild(empty);

            if (!province) {
                clubSelect.disabled = true;
                clubSelect.value = '';
                return;
            }

            var clubs = clubsForProvince(province, citySelect.value);
            clubSelect.disabled = clubs.length === 0;

            if (clubs.length === 0) {
                empty.textContent = 'No Scholarship Club has been set up for this area yet';
                return;
            }

            clubs.forEach(function (club) {
                var option = document.createElement('option');
                option.value = String(club.id);
                option.textContent = club.name;
                clubSelect.appendChild(option);
            });

            if (selectedClub && Array.prototype.some.call(clubSelect.options, function (option) {
                return option.value === String(selectedClub);
            })) {
                clubSelect.value = String(selectedClub);
            }
        }

        function syncProgramId() {
            var programId = clubSelect ? (clubSelect.value || '') : (citySelect.value || '');

            if (hiddenInput) {
                hiddenInput.value = programId;
            }
        }

        provinceSelect.addEventListener('change', function () {
            var province = provinceRecord(provinceSelect.value);
            setCityOptions(province, '');
            setClubOptions(province, '');
            syncProgramId();
        });

        citySelect.addEventListener('change', function () {
            setClubOptions(provinceRecord(provinceSelect.value), clubSelect ? clubSelect.value : '');
            syncProgramId();
        });

        if (clubSelect) {
            clubSelect.addEventListener('change', syncProgramId);
        }

        if (provinceSelect.value) {
            setCityOptions(provinceRecord(provinceSelect.value), selectedId);
            setClubOptions(provinceRecord(provinceSelect.value), selectedClubId);
            syncProgramId();
        }
    });
});
</script>
@endonce
