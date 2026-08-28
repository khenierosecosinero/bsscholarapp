@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/service-hours-page.css') }}">
@endpush

@section('page-content')
<div class="page-service-hours">

@php $pct = $hourStats['required'] > 0 ? round(($hourStats['approved'] / $hourStats['required']) * 100) : 0; @endphp

<section class="stats grid">
    <div class="stat card"><div class="stat-icon blue">&#128337;</div><div class="stat-body"><div class="stat-title">Required Hours</div><div class="stat-value">{{ number_format($hourStats['required'], 2) }} hours</div></div></div>
    <div class="stat card"><div class="stat-icon green">&#10003;</div><div class="stat-body"><div class="stat-title">Completed Hours</div><div class="stat-value green-text">{{ number_format($hourStats['approved'], 2) }} hours</div></div></div>
    <div class="stat card"><div class="stat-icon orange">&#128336;</div><div class="stat-body"><div class="stat-title">Pending Hours</div><div class="stat-value orange-text">{{ number_format($hourStats['pending'], 2) }} hours</div></div></div>
    <div class="stat card"><div class="stat-icon purple">&#9203;</div><div class="stat-body"><div class="stat-title">Remaining Hours</div><div class="stat-value purple-text">{{ number_format($hourStats['remaining'], 2) }} hours</div></div></div>
</section>

<div class="card progress-bar-card">
    <div class="progress-bar-header"><span>Overall Progress ({{ $semesterInfo['semester'] }})</span><strong>{{ $pct }}%</strong></div>
    <div class="progress-bar"><div class="progress-fill" style="width:{{ $pct }}%"></div></div>
</div>

<section class="service-hours-grid">
    <div class="service-main">
        <div class="card">
            <div class="tabs">
                @foreach(['all' => 'All Records', 'approved' => 'Approved', 'pending' => 'Pending', 'failed_to_check_in' => 'Failed to Check In', 'rejected' => 'Rejected'] as $key => $label)
                    <a href="{{ route('user.service-hours', ['tab' => $key]) }}" class="tab {{ $activeTab === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            @if($records->isEmpty())
                <p class="muted">No service hour records found.</p>
            @else
                <table class="table service-table">
                    <thead><tr><th>Event / Activity</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours Earned</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach($records as $rec)
                            <tr>
                                <td><div class="table-event">@if($rec->event?->image_url)<img src="{{ $rec->event->image_url }}" alt="">@endif {{ $rec->event?->title ?? 'N/A' }}</div></td>
                                <td>{{ $rec->event?->starts_at?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $rec->check_in?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $rec->check_out?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $rec->hoursLabel() }}</td>
                                <td><span class="badge {{ $rec->status === 'approved' ? 'confirmed' : ($rec->status === 'rejected' || $rec->status === 'failed_to_check_in' ? 'rejected' : 'pending') }}">{{ $rec->statusLabel() }}</span></td>
                                <td>{{ $rec->reviewNote() }}</td>
                                <td>@if($rec->event_id)<a href="{{ route('user.events', ['event' => $rec->event_id]) }}" class="btn outline small">View</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card how-it-works">
            <div class="card-header">How Service Hours Work</div>
            <div class="steps-grid">
                <div class="step"><div class="step-num">1</div><div class="step-icon">&#128197;</div><strong>Attend & Confirm</strong><p>Join events and confirm your participation.</p></div>
                <div class="step"><div class="step-num">2</div><div class="step-icon">&#128247;</div><strong>Check In / Out</strong><p>Log your attendance and attach a photo of your participation.</p></div>
                <div class="step"><div class="step-num">3</div><div class="step-icon">&#128269;</div><strong>Verification</strong><p>Scholar Staff reviews your photo, then approves your hours.</p></div>
                <div class="step"><div class="step-num">4</div><div class="step-icon">&#9201;</div><strong>Earn Hours</strong><p>Approved hours are credited to your account.</p></div>
            </div>
        </div>
    </div>

    @include('partials.service-hours-sidebar')
</section>

</div>

@endsection
