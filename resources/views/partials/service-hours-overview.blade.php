@php
    $hs = $hourStats ?? ['approved' => 0, 'pending' => 0, 'required' => 30, 'remaining' => 30];
    $pct = $hs['required'] > 0 ? round(($hs['approved'] / $hs['required']) * 100) : 0;
    $showButton = $showButton ?? true;
@endphp

<div class="card service-hours-overview-card">
    <div class="card-header">SERVICE HOURS OVERVIEW</div>

    <div class="sh-overview-body">
        <div class="sh-donut-wrap">
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
        </div>

        <div class="sh-stats-grid">
            <div class="sh-stat-col">
                <span class="dot completed" aria-hidden="true"></span>
                <span class="sh-stat-name">Completed</span>
                <strong class="sh-stat-value">{{ $hs['approved'] }} hrs</strong>
            </div>
            <div class="sh-stat-col">
                <span class="dot remaining" aria-hidden="true"></span>
                <span class="sh-stat-name">Remaining</span>
                <strong class="sh-stat-value">{{ $hs['remaining'] }} hrs</strong>
            </div>
            <div class="sh-stat-col">
                <span class="dot required" aria-hidden="true"></span>
                <span class="sh-stat-name">Required</span>
                <strong class="sh-stat-value">{{ $hs['required'] }} hrs</strong>
            </div>
        </div>
    </div>

    @if($showButton)
        <a href="{{ route('user.service-hours') }}" class="btn outline full sh-view-btn">View Service Hours</a>
    @endif
</div>
