@php
    $charts = $reportCharts ?? [];
    $chartOrder = ['scholars', 'staff', 'completed', 'participation'];
@endphp

<section class="admin-report-pies" aria-label="Location report charts">
    @foreach($chartOrder as $key)
        @php $chart = $charts[$key] ?? null; @endphp
        @if($chart)
            <article class="staff-card admin-pie-card">
                <div class="staff-card-header">
                    <h2>{{ $chart['title'] }}</h2>
                    <span class="staff-muted">Total {{ number_format($chart['total']) }}</span>
                </div>
                @if($chart['total'] > 0 && $chart['gradient'])
                    <div class="admin-pie-body">
                        <div
                            class="admin-pie-chart"
                            style="background: {{ $chart['gradient'] }}"
                            role="img"
                            aria-label="{{ $chart['title'] }} pie chart for {{ $locationLabel ?? 'the selected location' }}"
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
        @endif
    @endforeach
</section>
