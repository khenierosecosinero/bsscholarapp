@php
    $slices = $slices ?? [];
    $center = $center ?? '';
    $total = array_sum(array_map(fn ($slice) => (float) ($slice['value'] ?? 0), $slices));
    $cursor = 0;
    $stops = [];

    foreach ($slices as $slice) {
        $share = $total > 0 ? ((float) $slice['value'] / $total) * 100 : 0;
        $end = $cursor + $share;
        $stops[] = ($slice['color'] ?? '#9ca3af').' '.$cursor.'% '.$end.'%';
        $cursor = $end;
    }

    $gradient = $stops !== [] ? implode(', ', $stops) : '#e5e7eb 0% 100%';
@endphp

<div class="staff-donut-wrap">
    <div class="staff-donut" style="background: conic-gradient({{ $gradient }})">
        <div class="staff-donut-inner">{!! $center !!}</div>
    </div>
    @if(! empty($legend))
        <ul class="staff-legend">
            @foreach($legend as $item)
                <li><span class="staff-dot" style="background:{{ $item['color'] }}"></span> {{ $item['label'] }}</li>
            @endforeach
        </ul>
    @endif
</div>
