@php
    $regions = $reportRegions ?? [];
@endphp

<div class="admin-regional-reports" aria-label="Luzon, Visayas, and Mindanao reports">
    @foreach(['luzon', 'visayas', 'mindanao'] as $regionKey)
        @php $region = $regions[$regionKey] ?? []; @endphp
        <section class="admin-region-section" aria-label="{{ strtoupper($region['label'] ?? $regionKey) }}">
            <header class="admin-region-header">
                <h2>{{ strtoupper($region['label'] ?? $regionKey) }}</h2>
                <p class="staff-muted">{{ $region['academic_year'] ?? ($reportFilter['academic_year'] ?? '') }}</p>
            </header>

            <div class="admin-region-charts">
                @php $scholars = $region['scholars'] ?? ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0, 'chart' => ['empty' => true, 'slices' => [], 'title' => 'Scholars']]; @endphp
                <article class="staff-card admin-pie-card">
                    <div class="admin-pie-stat">
                        <div class="staff-stat-icon blue">👥</div>
                        <div class="staff-stat-body">
                            <h3>Scholars</h3>
                            <div class="value">{{ number_format((int) $scholars['total']) }}</div>
                            <div class="sub">Approved {{ number_format((int) ($scholars['approved'] ?? 0)) }} · Pending {{ number_format((int) ($scholars['pending'] ?? 0)) }} · Rejected {{ number_format((int) ($scholars['rejected'] ?? 0)) }}</div>
                        </div>
                    </div>
                    @include('partials.admin-report-pie-chart', ['regionLabel' => $region['label'] ?? $regionKey, 'chart' => $scholars['chart']])
                </article>

                @php $clubs = $region['clubs'] ?? ['total' => 0, 'chart' => ['empty' => true, 'slices' => [], 'title' => 'Scholarship Clubs']]; @endphp
                <article class="staff-card admin-pie-card">
                    <div class="admin-pie-stat">
                        <div class="staff-stat-icon teal">🏫</div>
                        <div class="staff-stat-body">
                            <h3>Scholarship Clubs</h3>
                            <div class="value">{{ number_format((int) $clubs['total']) }}</div>
                            <div class="sub">Registered clubs in this region</div>
                        </div>
                    </div>
                    @include('partials.admin-report-pie-chart', ['regionLabel' => $region['label'] ?? $regionKey, 'chart' => $clubs['chart']])
                </article>

                @php $completed = $region['completed'] ?? ['total' => 0, 'completed_pct' => 0, 'required' => 30, 'chart' => ['empty' => true, 'slices' => [], 'title' => 'Completed Students']]; @endphp
                <article class="staff-card admin-pie-card">
                    <div class="admin-pie-stat">
                        <div class="staff-stat-icon green">🎓</div>
                        <div class="staff-stat-body">
                            <h3>Completed Students (Completed Service Hours)</h3>
                            <div class="value">{{ number_format((int) $completed['total']) }}</div>
                            <div class="sub">{{ $completed['completed_pct'] }}% completed · Required {{ rtrim(rtrim(number_format((float) ($completed['required'] ?? 30), 2), '0'), '.') }} hours</div>
                        </div>
                    </div>
                    @include('partials.admin-report-pie-chart', ['regionLabel' => $region['label'] ?? $regionKey, 'chart' => $completed['chart']])
                </article>

                @php $participation = $region['participation'] ?? ['total' => 0, 'approved' => 0, 'pending' => 0, 'chart' => ['empty' => true, 'slices' => [], 'title' => 'Participation']]; @endphp
                <article class="staff-card admin-pie-card">
                    <div class="admin-pie-stat">
                        <div class="staff-stat-icon orange">🤝</div>
                        <div class="staff-stat-body">
                            <h3>Participation</h3>
                            <div class="value">{{ number_format((int) $participation['total']) }}</div>
                            <div class="sub">Approved {{ number_format((int) ($participation['approved'] ?? 0)) }} · Pending {{ number_format((int) ($participation['pending'] ?? 0)) }}</div>
                        </div>
                    </div>
                    @include('partials.admin-report-pie-chart', ['regionLabel' => $region['label'] ?? $regionKey, 'chart' => $participation['chart']])
                </article>
            </div>
        </section>
    @endforeach
</div>
