<section class="schedule-summary-section">
    <div class="schedule-summary-header">MY SCHEDULE SUMMARY</div>
    <div class="schedule-summary">
        <div class="summary-card green">
            <span class="summary-icon-wrap" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </span>
            <div class="summary-card-body">
                <strong>{{ $confirmed ?? 0 }}</strong>
                <span>Confirmed Events</span>
            </div>
        </div>

        <div class="summary-card orange">
            <span class="summary-icon-wrap" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </span>
            <div class="summary-card-body">
                <strong>{{ $pending ?? 0 }}</strong>
                <span>Pending Events</span>
            </div>
        </div>

        <div class="summary-card red">
            <span class="summary-icon-wrap" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </span>
            <div class="summary-card-body">
                <strong>{{ $cancelled ?? 0 }}</strong>
                <span>Cancelled Events</span>
            </div>
        </div>

        <div class="summary-card gray">
            <span class="summary-icon-wrap" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </span>
            <div class="summary-card-body">
                <strong>{{ $completed ?? 0 }}</strong>
                <span>Completed Events</span>
            </div>
        </div>
    </div>
</section>
