<div class="staff-card staff-report-period">
    <div class="staff-card-header">
        <h2>Academic Year Setup</h2>
    </div>
    <form method="GET" class="staff-report-period-form">
        <div class="staff-report-period-field">
            <label for="report-year">Academic Year</label>
            <select id="report-year" name="year" class="staff-select" onchange="this.form.submit()">
                @foreach($reportYearOptions as $year => $label)
                    <option value="{{ $year }}" @selected((int) $reportFilter['year_start'] === (int) $year)>AY {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="staff-report-period-field">
            <label for="report-semester">Semester</label>
            <select id="report-semester" name="semester" class="staff-select" onchange="this.form.submit()">
                @foreach($reportSemesterOptions as $value => $label)
                    <option value="{{ $value }}" @selected($reportFilter['semester'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </form>
    <p class="staff-report-period-banner">
        Showing <strong>{{ $reportFilter['academic_year'] }}</strong> · <strong>{{ $reportFilter['semester_label'] }}</strong>
    </p>
</div>
