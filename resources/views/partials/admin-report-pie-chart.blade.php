@php
    $chart = $chart ?? ['empty' => true, 'slices' => [], 'title' => 'Chart', 'gradient' => ''];
    $regionLabel = $regionLabel ?? '';
@endphp

@if(!empty($chart['empty']) || empty($chart['gradient']))
    <p class="admin-pie-empty">No data available</p>
@else
    <div class="admin-pie-body">
        <div
            class="admin-pie-chart"
            style="background: {{ $chart['gradient'] }}"
            role="img"
            aria-label="{{ strtoupper($regionLabel) }} {{ $chart['title'] ?? 'Report' }} pie chart"
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
@endif
