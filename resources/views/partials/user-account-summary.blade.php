@php
    $account = $account ?? auth()->user();
    $period = $activeAcademicPeriod ?? null;
    $memberSince = $account->created_at
        ? $account->created_at->timezone(config('app.timezone'))->format('M Y')
        : '—';
    $academicYear = $period ? $period->periodLabel() : '—';
    $semester = filled($period?->semester) ? $period->semester : '—';
    $statusTone = $account->dashboardAccountStatusTone();
@endphp

<div class="card account-summary">
    <div class="card-header">Account Summary</div>
    <div class="account-summary-list">
        <div class="account-summary-row">
            <div class="account-summary-label">
                <span class="account-summary-icon account-summary-icon-status" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12.5l4.2 4.2L19 7.5"/>
                    </svg>
                </span>
                <span>Account Status</span>
            </div>
            <span class="account-summary-badge account-summary-badge-{{ $statusTone }}">{{ $account->dashboardAccountStatusLabel() }}</span>
        </div>

        <div class="account-summary-row">
            <div class="account-summary-label">
                <span class="account-summary-icon account-summary-icon-calendar" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="5" width="16" height="15" rx="2"/>
                        <path d="M8 3.5v3M16 3.5v3M4 10h16"/>
                    </svg>
                </span>
                <span>Member Since</span>
            </div>
            <strong class="account-summary-value">{{ $memberSince }}</strong>
        </div>

        <div class="account-summary-row">
            <div class="account-summary-label">
                <span class="account-summary-icon account-summary-icon-school" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 10.5L12 6l8 4.5"/>
                        <path d="M7 12.2V17c0 .6 2.2 2 5 2s5-1.4 5-2v-4.8"/>
                        <path d="M20 11.2V16"/>
                    </svg>
                </span>
                <span>Academic Year</span>
            </div>
            <strong class="account-summary-value">{{ $academicYear }}</strong>
        </div>

        <div class="account-summary-row">
            <div class="account-summary-label">
                <span class="account-summary-icon account-summary-icon-books" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 5.5h5.2A2.8 2.8 0 0 1 14 8.3V19a2.2 2.2 0 0 0-2.2-1.6H6z"/>
                        <path d="M18 5.5h-5.2A2.8 2.8 0 0 0 10 8.3V19a2.2 2.2 0 0 1 2.2-1.6H18z"/>
                    </svg>
                </span>
                <span>Current Semester</span>
            </div>
            <strong class="account-summary-value">{{ $semester }}</strong>
        </div>
    </div>
</div>
