@extends('layouts.admin')

@section('page-content')

<div class="staff-card staff-report-period admin-report-year">
    <div class="staff-card-header">
        <h2>Academic Year</h2>
    </div>
    <form method="GET" action="{{ route('admin.reports') }}" class="staff-report-period-form">
        <div class="staff-report-period-field">
            <label for="report-year">Academic Year</label>
            <select id="report-year" name="year" class="staff-select" onchange="this.form.submit()">
                @foreach($reportYearOptions as $year => $label)
                    <option value="{{ $year }}" @selected((int) $reportFilter['year_start'] === (int) $year)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </form>
    <p class="staff-report-period-banner admin-report-year-banner">
        Showing <strong>Luzon</strong>, <strong>Visayas</strong>, and <strong>Mindanao</strong> for <strong>{{ $reportFilter['academic_year'] }}</strong>
    </p>
</div>

@include('partials.admin-regional-reports')

@endsection
