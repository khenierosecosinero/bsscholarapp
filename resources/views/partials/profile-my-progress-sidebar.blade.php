@php
    $hs = $hourStats ?? ['approved' => 0, 'pending' => 0, 'required' => 30, 'remaining' => 30];
    $pct = $hs['required'] > 0 ? round(($hs['approved'] / $hs['required']) * 100) : 0;
    $semesterLabel = strtoupper($semesterInfo['semester'] ?? '2ND SEMESTER');
@endphp

<div class="card profile-my-progress-card">
    <div class="card-header">MY PROGRESS ({{ $semesterLabel }})</div>

    <div class="progress-wrap compact profile-my-progress-wrap">
        <div class="progress-circle sh-donut">
            <svg viewBox="0 0 36 36" aria-hidden="true">
                <path class="bg" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                <path class="meter completed" stroke-dasharray="{{ $pct }},100" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
            </svg>
            <div class="progress-text">
                <span class="progress-value">{{ $hs['approved'] }} / {{ $hs['required'] }}</span>
                <small>Completed Hours</small>
            </div>
        </div>

        <div class="sh-sidebar-legend profile-progress-legend">
            <div class="sh-legend-row">
                <span class="sh-legend-label"><span class="legend-square completed" aria-hidden="true"></span>Completed</span>
                <strong>{{ $hs['approved'] }} hrs</strong>
            </div>
            <div class="sh-legend-row">
                <span class="sh-legend-label"><span class="legend-square remaining" aria-hidden="true"></span>Remaining</span>
                <strong>{{ $hs['remaining'] }} hrs</strong>
            </div>
            <div class="sh-legend-row">
                <span class="sh-legend-label"><span class="legend-square required-blue" aria-hidden="true"></span>Required</span>
                <strong>{{ $hs['required'] }} hrs</strong>
            </div>
        </div>
    </div>
</div>
