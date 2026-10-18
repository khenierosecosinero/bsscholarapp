<div class="staff-card staff-hours-progress-card">
    <div class="staff-card-header"><h2>4-Year Service Hour Progress</h2></div>
    @if(!empty($hourTracking['needs_start_year']) || empty($hourTracking['start_year']))
        <p class="staff-muted staff-hours-progress-intro">Start Academic Year has not been set for this scholar. Save the original scholarship starting year in Profile &amp; Settings → Academic Settings (or ask Scholar Staff to set it) so Year 1–Year 4 can be calculated.</p>
    @else
        <p class="staff-muted staff-hours-progress-intro">Year 1 starts at AY {{ $hourTracking['start_year'] }}–{{ $hourTracking['start_year'] + 1 }}. Years 2, 3, and 4 follow from that Start Academic Year. The selected view is {{ $hourTracking['academic_year'] }} ({{ $hourTracking['period']['semester'] }}). Changing the viewing filter does not change the Start Academic Year or remove this four-year structure.</p>
        <div class="staff-hours-years">
            @foreach($hourTracking['years'] as $yearRow)
                <article class="staff-hours-year {{ !empty($yearRow['is_current']) ? 'is-current' : '' }}">
                    <header class="staff-hours-year-head">
                        <strong>Year {{ $yearRow['program_year'] }}</strong>
                        <span>{{ $yearRow['academic_year'] }}</span>
                        <small>Completed {{ number_format($yearRow['completed'], 2) }} hrs</small>
                    </header>
                    <div class="staff-hours-semesters">
                        @foreach($yearRow['semesters'] as $semesterRow)
                            <div class="staff-hours-semester-card {{ !empty($semesterRow['is_current']) ? 'is-current' : '' }}">
                                <strong>{{ $semesterRow['semester'] }}</strong>
                                <p>Completed: <strong>{{ number_format($semesterRow['approved'], 2) }}</strong> / {{ number_format($semesterRow['required'], 2) }}</p>
                                <p>Pending: <strong>{{ number_format($semesterRow['pending'], 2) }}</strong></p>
                                <p>Remaining: <strong>{{ number_format($semesterRow['remaining'], 2) }}</strong></p>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
