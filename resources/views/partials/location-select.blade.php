@php
    $fieldId = $fieldId ?? 'scholarship_program_id';
    $selectedId = old('scholarship_program_id', $selectedId ?? '');
    $groups = $programGroups ?? ['cities' => collect(), 'provinces' => collect()];
    $programs = ($groups['cities'] ?? collect())->merge($groups['provinces'] ?? collect());
    $selectedProgram = $programs->firstWhere('id', (int) $selectedId);
    $placeholder = $placeholder ?? 'Select scholar program...';
    $helpText = $helpText ?? 'Choose the city or province scholar program you belong to.';
@endphp

<div class="location-select-field" data-location-select>
    <label class="sr-only" for="{{ $fieldId }}">Scholar Program</label>
    <input
        type="search"
        id="{{ $fieldId }}_search"
        class="form-input location-search-input"
        placeholder="Search scholar program..."
        autocomplete="off"
        aria-controls="{{ $fieldId }}_listbox"
        aria-autocomplete="list"
        value="{{ $selectedProgram?->programLabel() }}"
    >
    <select
        id="{{ $fieldId }}"
        name="scholarship_program_id"
        class="form-input form-select location-select-native"
        required
        size="8"
    >
        <option value="" disabled {{ $selectedId ? '' : 'selected' }}>{{ $placeholder }}</option>
        @include('partials.program-select-options', ['programGroups' => $groups, 'selectedId' => $selectedId])
    </select>
    <p class="muted small location-select-help">{{ $helpText }}</p>
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
