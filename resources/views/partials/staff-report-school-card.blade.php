@php
    $kind = $kind ?? 'completion';
@endphp

<article class="staff-report-school-card">
    <header class="staff-report-school-head">
        <h3>{{ $school['name'] }} — {{ $school['percent'] }}%</h3>
        <p class="staff-report-school-total"><strong>Total:</strong> {{ number_format((int) $school['total']) }}</p>
        <ul class="staff-report-school-stats">
            @if($kind === 'hours' || $kind === 'completion')
                <li><strong>Completed:</strong> {{ $school['completed'] }}</li>
                <li><strong>In Progress:</strong> {{ $school['in_progress'] }}</li>
                <li><strong>Not Started:</strong> {{ $school['not_started'] }}</li>
            @elseif($kind === 'attendance')
                <li><strong>Present / Attended:</strong> {{ $school['present'] }}</li>
                <li><strong>Failed to Check In:</strong> {{ $school['failedCheckIn'] }}</li>
                <li><strong>Absent:</strong> {{ $school['absent'] }}</li>
            @else
                <li><strong>Participated / Completed:</strong> {{ $school['participated'] }}</li>
                <li><strong>In Progress:</strong> {{ $school['in_progress'] }}</li>
                <li><strong>Not Started:</strong> {{ $school['not_started'] }}</li>
            @endif
        </ul>
    </header>

    <div class="staff-report-school-chart">
        @if($kind === 'hours' || $kind === 'completion' || $kind === 'participation')
            @include('partials.staff-report-donut', [
                'slices' => [
                    ['value' => $school['completed'] ?? $school['participated'], 'color' => '#22c55e'],
                    ['value' => $school['in_progress'], 'color' => '#1890ff'],
                    ['value' => $school['not_started'], 'color' => '#9ca3af'],
                ],
                'legend' => [
                    ['color' => '#22c55e', 'label' => ($kind === 'participation' ? 'Participated / Completed: ' : 'Completed: ').($school['completed'] ?? $school['participated'])],
                    ['color' => '#1890ff', 'label' => 'In Progress: '.$school['in_progress']],
                    ['color' => '#9ca3af', 'label' => 'Not Started: '.$school['not_started']],
                ],
                'center' => $school['total'].'<br>Scholars',
            ])
        @else
            @include('partials.staff-report-donut', [
                'slices' => [
                    ['value' => $school['present'], 'color' => '#22c55e'],
                    ['value' => $school['failedCheckIn'], 'color' => '#991b1b'],
                    ['value' => $school['absent'], 'color' => '#9ca3af'],
                ],
                'legend' => [
                    ['color' => '#22c55e', 'label' => 'Present / Attended: '.$school['present']],
                    ['color' => '#991b1b', 'label' => 'Failed to Check In: '.$school['failedCheckIn']],
                    ['color' => '#9ca3af', 'label' => 'Absent: '.$school['absent']],
                ],
                'center' => $school['total'].'<br>Scholars',
            ])
        @endif
    </div>
</article>
