@php
    $groups = $programGroups ?? ['cities' => collect(), 'provinces' => collect()];
    $selectedId = $selectedId ?? '';
    $cityPrograms = $groups['cities'] ?? collect();
    $provincePrograms = $groups['provinces'] ?? collect();
@endphp

@if($cityPrograms->isNotEmpty())
    <optgroup label="City Scholar Programs">
        @foreach($cityPrograms as $program)
            <option
                value="{{ $program->id }}"
                data-label="{{ $program->programLabel() }}"
                data-region="{{ $program->region_name }}"
                data-program-type="city_municipality"
                {{ (string) $selectedId === (string) $program->id ? 'selected' : '' }}
            >
                {{ $program->programLabel() }}
            </option>
        @endforeach
    </optgroup>
@endif

@if($provincePrograms->isNotEmpty())
    <optgroup label="Province Scholar Programs">
        @foreach($provincePrograms as $program)
            <option
                value="{{ $program->id }}"
                data-label="{{ $program->programLabel() }}"
                data-region="{{ $program->region_name }}"
                data-program-type="province"
                {{ (string) $selectedId === (string) $program->id ? 'selected' : '' }}
            >
                {{ $program->programLabel() }}
            </option>
        @endforeach
    </optgroup>
@endif
