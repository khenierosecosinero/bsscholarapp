@php
    $fieldId = $fieldId ?? 'scholarship_program_id';
    $selectedId = old('scholarship_program_id', $selectedId ?? '');
    $tree = $locationTree ?? [];
    $requireCity = $requireCity ?? true;
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
</style>
@endonce

<div class="location-cascade" data-location-cascade data-require-city="{{ $requireCity ? '1' : '0' }}">
    <input
        type="hidden"
        id="{{ $fieldId }}"
        name="scholarship_program_id"
        value="{{ $selectedId }}"
        required
    >

    <div>
        <label class="location-cascade-label" for="{{ $fieldId }}_province">Province</label>
        <select
            id="{{ $fieldId }}_province"
            class="form-input form-select"
            data-location-province
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
            required
            {{ $selectedProvinceName ? '' : 'disabled' }}
        >
            <option value="">{{ $requireCity ? 'Select municipality or city' : 'Entire province or a municipality/city' }}</option>
        </select>
    </div>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tree = @json($tree);

    document.querySelectorAll('[data-location-cascade]').forEach(function (field) {
        var hiddenInput = field.querySelector('input[type="hidden"]');
        var provinceSelect = field.querySelector('[data-location-province]');
        var citySelect = field.querySelector('[data-location-city]');
        var requireCity = field.getAttribute('data-require-city') === '1';
        var selectedId = hiddenInput ? String(hiddenInput.value || '') : '';

        if (!hiddenInput || !provinceSelect || !citySelect) {
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

            if (!province) {
                citySelect.disabled = true;
                return;
            }

            citySelect.disabled = false;

            if (!requireCity && province.id) {
                var allOption = document.createElement('option');
                allOption.value = String(province.id);
                allOption.textContent = 'Entire province — ' + province.name;
                if (selectedCityId && String(selectedCityId) === String(province.id)) {
                    allOption.selected = true;
                }
                citySelect.appendChild(allOption);
            }

            (province.cities || []).forEach(function (city) {
                var option = document.createElement('option');
                option.value = String(city.id);
                option.textContent = city.name;
                if (selectedCityId && String(selectedCityId) === String(city.id)) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });

            if (!selectedCityId && !requireCity && province.id) {
                citySelect.value = String(province.id);
            }
        }

        function syncHidden() {
            hiddenInput.value = citySelect.value || '';
        }

        provinceSelect.addEventListener('change', function () {
            var province = provinceRecord(provinceSelect.value);
            setCityOptions(province, '');
            syncHidden();
        });

        citySelect.addEventListener('change', syncHidden);

        if (provinceSelect.value) {
            setCityOptions(provinceRecord(provinceSelect.value), selectedId);
            syncHidden();
        }
    });
});
</script>
@endonce
