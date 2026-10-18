@php
    $viewPeriod = $academicPeriod ?? null;
    $yearOptions = $academicYearOptions ?? [];
    $yearFilter = $adminAcademicYearFilter ?? null;
    $compact = ! empty($compact);
@endphp

@if(!empty($yearOptions) && $viewPeriod)
    <div class="staff-card admin-academic-year-selector{{ $compact ? ' admin-academic-year-selector-compact' : '' }}">
        <div class="staff-card-header admin-academic-year-selector-header">
            <h2>{{ $title ?? 'Academic Year' }}</h2>
        </div>
        <form method="POST" action="{{ route('academic.period.update') }}" class="admin-academic-year-selector-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="semester" value="{{ $viewPeriod->semester }}">
            <div class="admin-academic-year-selector-field">
                <label for="{{ $inputId ?? 'admin-portal-academic-year' }}">Viewing Academic Year</label>
                <select
                    id="{{ $inputId ?? 'admin-portal-academic-year' }}"
                    name="year_start"
                    class="staff-select admin-academic-year-selector-select"
                    aria-label="Academic year"
                    onchange="this.form.submit()"
                >
                    @foreach($yearOptions as $year => $label)
                        <option value="{{ $year }}" @selected((int) $viewPeriod->year_start === (int) $year)>AY {{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        @if(!empty($yearFilter['academic_year']))
            <div
                class="staff-report-period-banner admin-report-year-banner admin-academic-year-selector-banner"
                role="status"
            >
                <span class="admin-academic-year-selector-banner-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" focusable="false">
                        <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.12"/>
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
                        <path d="M12 10.5V17M12 7.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <p class="admin-academic-year-selector-banner-text">
                    Showing Administration data for
                    <strong>{{ $yearFilter['academic_year'] }}</strong>
                    ·
                    <strong>{{ $yearFilter['semester_label'] ?? 'All Semesters' }}</strong>
                </p>
            </div>
        @endif
    </div>
@endif
