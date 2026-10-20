@php
    $requiredFallback = \App\Services\ScholarService::REQUIRED_HOURS;
    $hs = $hourStats ?? ['approved' => 0, 'pending' => 0, 'required' => $requiredFallback, 'remaining' => $requiredFallback];
    $pct = $hs['required'] > 0 ? min(100, (int) round(($hs['approved'] / $hs['required']) * 100)) : 0;
@endphp

<div class="card hours-overview service-hours-sidebar-card">
    <div class="card-header">SERVICE HOURS OVERVIEW</div>
    @if(!empty($semesterInfo['academic_year']))
        <p class="muted small" style="margin:0 0 12px">{{ $semesterInfo['academic_year'] }} · {{ $semesterInfo['semester'] }}</p>
    @endif

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
