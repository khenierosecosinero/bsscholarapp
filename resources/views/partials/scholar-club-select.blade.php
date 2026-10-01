@php
    $clubs = $clubs ?? [];
    $selectedClubId = old('scholarship_club_id', $selectedClubId ?? '');
    $selectedSchoolId = old('scholarship_club_school_id', $selectedSchoolId ?? '');
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

    .auth-page .location-cascade select.form-input,
    .auth-page .location-cascade input.form-input {
        margin: 0;
        width: 100%;
        box-sizing: border-box;
    }

    .auth-page .location-cascade input[readonly] {
        background: #f3f4f6;
        color: #374151;
        cursor: default;
    }
</style>
@endonce

<div class="location-cascade" data-scholar-club-select>
    <div>
        <label class="location-cascade-label" for="scholarship_club_id">Scholarship Club</label>
        <select
            id="scholarship_club_id"
            class="form-input form-select"
            name="scholarship_club_id"
            data-scholar-club
            required
        >
            <option value="">{{ count($clubs) ? 'Select scholarship club' : 'No Scholarship Club has been set up yet' }}</option>
            @foreach($clubs as $club)
                <option
                    value="{{ $club['id'] }}"
                    data-city="{{ $club['city'] ?? '' }}"
                    data-province="{{ $club['province'] ?? '' }}"
                    data-schools="{{ json_encode($club['schools'] ?? []) }}"
                    {{ (string) $selectedClubId === (string) $club['id'] ? 'selected' : '' }}
                >
                    {{ $club['name'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="location-cascade-label" for="scholarship_club_province">Province</label>
        <input
            class="form-input"
            type="text"
            id="scholarship_club_province"
            data-scholar-club-province
            value=""
            placeholder="Province"
            readonly
            tabindex="-1"
        >
    </div>

    <div>
        <label class="location-cascade-label" for="scholarship_club_city">Municipality / City</label>
        <input
            class="form-input"
            type="text"
            id="scholarship_club_city"
            data-scholar-club-city
            value=""
            placeholder="Municipality / City"
            readonly
            tabindex="-1"
        >
    </div>

    <div>
        <label class="location-cascade-label" for="scholarship_club_school_id">School/University</label>
        <select
            id="scholarship_club_school_id"
            class="form-input form-select"
            name="scholarship_club_school_id"
            data-scholar-school
            required
            {{ $selectedClubId ? '' : 'disabled' }}
        >
            <option value="">Select school/university</option>
        </select>
    </div>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-scholar-club-select]').forEach(function (field) {
        var clubSelect = field.querySelector('[data-scholar-club]');
        var provinceInput = field.querySelector('[data-scholar-club-province]');
        var cityInput = field.querySelector('[data-scholar-club-city]');
        var schoolSelect = field.querySelector('[data-scholar-school]');
        var selectedSchoolId = @json($selectedSchoolId);

        if (!clubSelect || !provinceInput || !cityInput || !schoolSelect) {
            return;
        }

        function parseSchools(option) {
            if (!option) {
                return [];
            }

            try {
                return JSON.parse(option.getAttribute('data-schools') || '[]');
            } catch (error) {
                return [];
            }
        }

        function fillClubDetails() {
            var option = clubSelect.options[clubSelect.selectedIndex];
            var hasClub = option && option.value;
            var schools = hasClub ? parseSchools(option) : [];

            provinceInput.value = hasClub ? (option.getAttribute('data-province') || '') : '';
            cityInput.value = hasClub ? (option.getAttribute('data-city') || '') : '';

            schoolSelect.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            schoolSelect.appendChild(empty);

            if (!hasClub) {
                empty.textContent = 'Select a Scholarship Club first';
                schoolSelect.disabled = true;
                return;
            }

            if (schools.length === 0) {
                empty.textContent = 'No School/University has been added for this club yet';
                schoolSelect.disabled = true;
                return;
            }

            empty.textContent = 'Select school/university';
            schoolSelect.disabled = false;
            schools.forEach(function (school) {
                var schoolOption = document.createElement('option');
                schoolOption.value = String(school.id);
                schoolOption.textContent = school.name;
                if (String(selectedSchoolId) === String(school.id)) {
                    schoolOption.selected = true;
                }
                schoolSelect.appendChild(schoolOption);
            });
        }

        clubSelect.addEventListener('change', function () {
            selectedSchoolId = '';
            fillClubDetails();
        });
        fillClubDetails();
    });
});
</script>
@endonce
