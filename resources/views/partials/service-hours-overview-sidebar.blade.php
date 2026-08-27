@php
    $hs = $hourStats ?? ['approved' => 0, 'pending' => 0, 'required' => 30, 'remaining' => 30];
    $pct = $hs['required'] > 0 ? round(($hs['approved'] / $hs['required']) * 100) : 0;
    $selectedYear = old('year_start', $user->academic_year_start ?? $globalAcademicSettings->year_start ?? now()->year);
    $selectedSemester = old('semester', $user->semester ?? $globalAcademicSettings->semester ?? '2nd Semester');
@endphp

<div class="card hours-overview service-hours-sidebar-card">
    <div class="card-header">SERVICE HOURS OVERVIEW</div>

    <form method="POST" action="{{ route('user.profile.academic') }}" class="sh-sidebar-filters">
        @csrf @method('PUT')
        <div class="sh-filter-field">
            <select name="year_start" class="sh-filter-select" aria-label="Academic year" onchange="this.form.submit()">
                @foreach($academicYearOptions ?? [] as $year => $label)
                    <option value="{{ $year }}" {{ (int) $selectedYear === (int) $year ? 'selected' : '' }}>AY {{ $year }} - {{ $year + 1 }}</option>
                @endforeach
            </select>
        </div>
        <div class="sh-filter-field">
            <select name="semester" class="sh-filter-select" aria-label="Semester" onchange="this.form.submit()">
                @foreach($semesterOptions ?? ['1st Semester', '2nd Semester'] as $option)
                    <option value="{{ $option }}" {{ $selectedSemester === $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="progress-wrap compact sh-sidebar-progress">
        <div class="progress-circle sh-donut">
            <svg viewBox="0 0 36 36" aria-hidden="true">
                <path class="bg" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                <path class="meter" stroke-dasharray="{{ $pct }},100" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
            </svg>
            <div class="progress-text">
                <span class="progress-value">{{ $hs['approved'] }} / {{ $hs['required'] }}</span>
                <small>Completed Hours</small>
            </div>
        </div>

        <div class="sh-sidebar-legend">
            <div class="sh-legend-row">
                <span class="sh-legend-label"><span class="legend-square required-dark" aria-hidden="true"></span>Required Hours</span>
                <strong>{{ $hs['required'] }}</strong>
            </div>
            <div class="sh-legend-row">
                <span class="sh-legend-label"><span class="dot completed" aria-hidden="true"></span>Completed Hours</span>
                <strong>{{ $hs['approved'] }}</strong>
            </div>
            <div class="sh-legend-row">
                <span class="sh-legend-label"><span class="dot remaining" aria-hidden="true"></span>Remaining Hours</span>
                <strong>{{ $hs['remaining'] }}</strong>
            </div>
        </div>
    </div>

    @if(($active ?? '') !== 'service-hours')
        <a href="{{ route('user.service-hours') }}" class="btn outline full sh-view-btn">View Service Hours</a>
    @endif
</div>
