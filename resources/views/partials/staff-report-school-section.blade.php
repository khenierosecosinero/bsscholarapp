@php
    $kind = $kind ?? 'completion';
    $schools = $report['schools'] ?? [];
    $showShare = ! empty($report['show_school_breakdown']);
@endphp

<section class="staff-report-schools" style="margin-top:20px">
    <h2 class="staff-report-schools-title">School/University Reports</h2>
    @if($showShare)
        @include('partials.staff-report-school-share', ['schools' => $schools])
    @endif
    @forelse($schools as $school)
        @include('partials.staff-report-school-card', ['school' => $school, 'kind' => $kind])
    @empty
        <p class="staff-muted">No School/University data for this period.</p>
    @endforelse
</section>
