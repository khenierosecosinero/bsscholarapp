@php
    $schools = $schools ?? [];
    $colors = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5'];
    $slices = [];
    $legend = [];

    foreach ($schools as $index => $school) {
        $color = $colors[$index % count($colors)];
        $slices[] = ['value' => $school['percent'], 'color' => $color];
        $legend[] = ['color' => $color, 'label' => $school['name'].' ('.$school['percent'].'%)'];
    }

    $percentTotal = array_sum(array_column($schools, 'percent'));
@endphp

<div class="staff-card staff-report-school-share">
    <div class="staff-card-header"><h2>School/University Share</h2></div>
    @if($schools === [])
        <p class="staff-muted" style="margin:0">No School/University data for this period.</p>
    @else
        @include('partials.staff-report-donut', [
            'slices' => $slices,
            'legend' => $legend,
            'center' => $percentTotal.'%<br>Schools',
        ])
        <p class="staff-muted staff-report-percent-note">Percentages are the share of scholars in each School/University and add up to {{ $percentTotal }}%.</p>
    @endif
</div>
