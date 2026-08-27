@php
    $fieldId = $fieldId ?? 'scholarship_program_id';
    $selectedId = old('scholarship_program_id', $selectedId ?? '');
    $programs = $scholarshipPrograms ?? collect();
    $selectedProgram = $programs->firstWhere('id', (int) $selectedId);
@endphp

<div class="location-select-field" data-location-select>
    <label class="sr-only" for="{{ $fieldId }}">Municipality/City/Province</label>
    <input
        type="search"
        id="{{ $fieldId }}_search"
        class="form-input location-search-input"
        placeholder="Search municipality, city, or province..."
        autocomplete="off"
        aria-controls="{{ $fieldId }}_listbox"
        aria-autocomplete="list"
        value="{{ $selectedProgram?->dropdownLabel() }}"
    >
    <select
        id="{{ $fieldId }}"
        name="scholarship_program_id"
        class="form-input form-select location-select-native"
        required
        size="8"
    >
        <option value="" disabled {{ $selectedId ? '' : 'selected' }}>Select Municipality/City/Province</option>
        @foreach($programs as $program)
            <option
                value="{{ $program->id }}"
                data-label="{{ $program->dropdownLabel() }}"
                data-region="{{ $program->region_name }}"
                {{ (string) $selectedId === (string) $program->id ? 'selected' : '' }}
            >
                {{ $program->dropdownLabel() }}
            </option>
        @endforeach
    </select>
    <p class="muted small location-select-help">Search and select your official Philippine municipality, city, or province.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-location-select]').forEach(function (field) {
        var searchInput = field.querySelector('.location-search-input');
        var select = field.querySelector('.location-select-native');
        if (!searchInput || !select) {
            return;
        }

        var options = Array.prototype.slice.call(select.options).filter(function (option) {
            return option.value !== '';
        });

        function filterOptions(query) {
            var normalized = query.trim().toLowerCase();
            var visibleCount = 0;

            options.forEach(function (option) {
                var label = (option.getAttribute('data-label') || option.textContent || '').toLowerCase();
                var region = (option.getAttribute('data-region') || '').toLowerCase();
                var matches = normalized === '' || label.indexOf(normalized) !== -1 || region.indexOf(normalized) !== -1;
                option.hidden = !matches;
                if (matches) {
                    visibleCount++;
                }
            });

            select.size = Math.min(8, Math.max(visibleCount + 1, 2));
        }

        searchInput.addEventListener('input', function () {
            filterOptions(searchInput.value);
        });

        searchInput.addEventListener('focus', function () {
            filterOptions(searchInput.value);
            select.size = Math.min(8, Math.max(options.length + 1, 2));
        });

        select.addEventListener('change', function () {
            var selected = select.options[select.selectedIndex];
            if (selected && selected.value !== '') {
                searchInput.value = selected.getAttribute('data-label') || selected.textContent;
            }
        });

        filterOptions(searchInput.value);
    });
});
</script>
