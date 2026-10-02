@php $o = $documentOverview ?? $docOverview ?? null; @endphp

@if($o)
    <div class="card documents-overview-sidebar-card">
        <div class="card-header">DOCUMENTS OVERVIEW</div>

        <div class="progress-wrap compact">
            <div class="progress-circle">
                <svg viewBox="0 0 36 36">
                    <path class="bg" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                    <path class="meter" stroke-dasharray="{{ $o['completion_pct'] }},100" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                </svg>
                <div class="progress-text">{{ $o['approved'] }} / {{ $o['total'] }}<br><small>Approved</small></div>
            </div>

            <div class="hours-list">
                <div><span class="dot completed"></span> Approved <strong>{{ $o['approved'] }}</strong></div>
                <div><span class="dot pending-dot"></span> Pending <strong>{{ $o['pending'] }}</strong></div>
                <div><span class="dot rejected-dot"></span> Rejected <strong>{{ $o['rejected'] }}</strong></div>
                <div><span class="dot not-submitted-dot"></span> Not Submitted <strong>{{ $o['not_submitted'] }}</strong></div>
            </div>
        </div>
    </div>
@endif
