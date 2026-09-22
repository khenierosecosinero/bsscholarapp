@php
    $sections = [
        'city' => $reportCharts['city'] ?? null,
        'province' => $reportCharts['province'] ?? null,
    ];
    $chartOrder = ['scholars', 'staff', 'completed', 'participation'];
@endphp

<section class="admin-report-program-split" aria-label="City and Province scholarship program charts">
    @foreach($sections as $type => $section)
        @php
            $sectionTitle = $section['title'] ?? ($type === 'city' ? 'City Scholarship Program Data' : 'Province Scholarship Program Data');
            $charts = $section['charts'] ?? [];
        @endphp
        <section class="admin-report-program-column" aria-label="{{ $sectionTitle }}">
            <div class="staff-card admin-report-program-header">
                <div class="staff-card-header">
                    <h2>{{ $sectionTitle }}</h2>
                </div>
                <p class="staff-muted">{{ $section['subtitle'] ?? '' }}</p>
            </div>

            <div class="admin-report-pies">
                @foreach($chartOrder as $key)
                    @php $chart = $charts[$key] ?? null; @endphp
                    <article class="staff-card admin-pie-card">
                        <div class="staff-card-header">
                            <h2>{{ $chart['title'] ?? ucfirst($key) }}</h2>
                            @if($chart)
                                <span class="staff-muted">Total {{ number_format($chart['total']) }}</span>
                            @endif
                        </div>
                        @if($chart && $chart['total'] > 0 && $chart['gradient'])
                            <div class="admin-pie-body">
                                <div
                                    class="admin-pie-chart"
                                    style="background: {{ $chart['gradient'] }}"
                                    role="img"
                                    aria-label="{{ $sectionTitle }} {{ $chart['title'] }} pie chart"
                                ></div>
                                <ul class="staff-legend admin-pie-legend">
                                    @foreach($chart['slices'] as $slice)
                                        <li>
                                            <span class="staff-dot" style="background:{{ $slice['color'] }}"></span>
                                            <span>{{ $slice['label'] }}</span>
                                            <strong>{{ number_format($slice['value']) }} ({{ $slice['pct'] }}%)</strong>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <p class="admin-pie-empty">No data available</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</section>
