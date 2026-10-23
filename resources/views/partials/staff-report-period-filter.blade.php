@php
    $configuredPeriod = $academicPeriod ?? null;
    $periodYear = $reportFilter['academic_year'] ?? ($configuredPeriod ? 'AY '.$configuredPeriod->year_start.'–'.$configuredPeriod->year_end : '');
    $periodSemester = $reportFilter['semester_label'] ?? ($configuredPeriod->semester ?? '');
    $selectedReportYear = $selectedReportYear ?? (string) ($reportFilter['year_start'] ?? '');
@endphp
<div class="staff-card staff-report-period">
    <div class="staff-card-header">
        <h2>Report Filters</h2>
    </div>
    <form method="GET" class="staff-report-period-form">
        <div class="staff-report-period-field">
            <label for="report-year">Academic Year</label>
            <select id="report-year" name="year" class="staff-select" onchange="this.form.submit()">
                <option value="all" @selected($selectedReportYear === 'all')>All Academic Years</option>
                @foreach($reportYearOptions as $yearStart => $label)
                    <option value="{{ $yearStart }}" @selected((string) $selectedReportYear === (string) $yearStart)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="staff-report-period-field">
            <label for="report-school">School/University</label>
            <select id="report-school" name="school" class="staff-select" onchange="this.form.submit()">
                @foreach($reportSchoolOptions as $option)
                    <option value="{{ $option['key'] }}" @selected($selectedSchoolKey === $option['key'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
    </form>
    <p class="staff-report-period-banner">
        Showing <strong>{{ $staff->scholarshipClubLabel() }}</strong> · <strong>{{ $periodYear }}</strong> · <strong>{{ $periodSemester }}</strong> · <strong>{{ $selectedSchoolLabel }}</strong>
        <span class="staff-muted"> · Set in Settings → General Settings</span>
    </p>
    @if(!empty($report['empty']))
        <p class="staff-report-empty">No records for {{ $periodYear }}.</p>
    @endif
</div>
