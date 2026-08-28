<aside class="service-sidebar">
    <div class="card sh-semester-summary-card">
        <div class="card-header">SEMESTER SUMMARY</div>

        <div class="progress-wrap compact center sh-summary-donut-wrap">
            <div class="progress-circle lg sh-summary-donut">
                <svg viewBox="0 0 36 36" aria-hidden="true">
                    <path class="bg" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                    @if($semesterSummary['approved_arc'] > 0)
                        <path class="meter completed" stroke-dasharray="{{ $semesterSummary['approved_arc'] }}, 100" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                    @endif
                    @if($semesterSummary['pending_arc'] > 0)
                        <path class="meter pending" stroke-dasharray="{{ $semesterSummary['pending_arc'] }}, 100" stroke-dashoffset="-{{ $semesterSummary['pending_offset'] }}" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                    @endif
                    @if($semesterSummary['remaining_arc'] > 0)
                        <path class="meter remaining-seg" stroke-dasharray="{{ $semesterSummary['remaining_arc'] }}, 100" stroke-dashoffset="-{{ $semesterSummary['remaining_offset'] }}" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                    @endif
                </svg>
                <div class="progress-text">
                    <span class="progress-value">{{ $semesterSummary['approved'] }} / {{ $semesterSummary['required'] }}</span>
                    <small>Completed</small>
                </div>
            </div>
        </div>

        <div class="sh-summary-legend">
            <div class="sh-summary-legend-row">
                <span class="sh-summary-legend-label"><span class="dot completed" aria-hidden="true"></span>Completed Hours</span>
                <strong>{{ $semesterSummary['approved'] }} ({{ $semesterSummary['completed_pct'] }}%)</strong>
            </div>
            <div class="sh-summary-legend-row">
                <span class="sh-summary-legend-label"><span class="dot pending-dot" aria-hidden="true"></span>Pending Hours</span>
                <strong>{{ $semesterSummary['pending'] }} ({{ $semesterSummary['pending_pct'] }}%)</strong>
            </div>
            <div class="sh-summary-legend-row">
                <span class="sh-summary-legend-label"><span class="dot remaining" aria-hidden="true"></span>Remaining Hours</span>
                <strong>{{ $semesterSummary['remaining'] }} ({{ $semesterSummary['remaining_pct'] }}%)</strong>
            </div>
        </div>
    </div>

    <div class="card sh-semester-chart-card">
        <div class="card-header">SERVICE HOURS PER SEMESTER</div>

        <div class="sh-chart-legend">
            <span class="sh-chart-legend-item"><span class="sh-chart-legend-pill completed" aria-hidden="true"></span>Completed</span>
            <span class="sh-chart-legend-item"><span class="sh-chart-legend-line" aria-hidden="true"></span>Required</span>
        </div>

        <div class="sh-bar-chart-wrap" style="--required-pct: {{ $semesterHoursChart['required_line'] }}%">
            <div class="sh-bar-chart-frame">
                <div class="sh-bar-y-gutter" aria-hidden="true">
                    <div class="sh-bar-y-axis">
                        @foreach([40, 30, 20, 10, 0] as $tick)
                            <span>{{ $tick }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="sh-bar-chart-content">
                    <div class="sh-bar-threshold" aria-hidden="true"></div>

                    <div class="sh-bar-semesters">
                        @foreach($semesterHoursChart['semesters'] as $semester)
                            <div class="sh-bar-semester">
                                <div class="sh-bar-semester-value">
                                    {{ $semester['approved'] > 0 ? rtrim(rtrim(number_format($semester['approved'], 2), '0'), '.') : '0' }}
                                </div>
                                <div class="sh-bar-semester-plot">
                                    <div class="sh-bar-required-tag" aria-hidden="true">
                                        <span>{{ $semesterHoursChart['required'] }}</span>
                                        <i></i>
                                    </div>
                                    <div class="sh-bar-track">
                                        <div class="sh-bar-fill" style="height:{{ $semester['bar_height'] }}%"></div>
                                    </div>
                                </div>
                                <div class="sh-bar-semester-name">{{ $semester['short_label'] }} Semester</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="sh-bar-hours-title">Hours</div>
        </div>
    </div>

    <div class="card notes-card sh-notes-card">
        <div class="card-header">NOTES</div>
        <ul>
            <li>You are required to complete {{ $hourStats['required'] }} service hours per semester.</li>
            <li>Service hours are credited only after Scholar Staff verifies your attendance and participation photo.</li>
            <li>Scholars who fail to check in receive 0 service hours for that event.</li>
        </ul>
    </div>
</aside>
