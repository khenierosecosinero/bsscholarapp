@extends('layouts.staff')

@section('page-content')

<div class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Event Details</h2>
            <a href="{{ route('staff.events') }}" class="staff-card-link">&larr; Back</a>
        </div>

        @if($event->image_url)
            <img src="{{ $event->image_url }}" alt="{{ $event->title }}" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;margin-bottom:16px">
        @endif

        @php
            $statusClass = match($event->status ?? 'upcoming') {
                'confirmed', 'completed' => 'green',
                'pending' => 'orange',
                'ongoing' => 'blue',
                default => 'gray',
            };
        @endphp

        <h3 style="margin:0 0 8px">{{ $event->title }}</h3>
        <span class="staff-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $event->status ?? 'upcoming')) }}</span>

        @if($event->description)
            <p class="staff-muted" style="margin:16px 0">{{ $event->description }}</p>
        @endif

        <div class="staff-list-item"><div><strong>Location</strong><div class="staff-muted">{{ $event->location }}</div></div></div>
        <div class="staff-list-item"><div><strong>Date &amp; Time</strong><div class="staff-muted">{{ $event->starts_at->format('M j, Y g:i A') }} – {{ $event->ends_at->format('g:i A') }}</div></div></div>
        <div class="staff-list-item"><div><strong>Service Hours</strong><div class="staff-muted">{{ number_format((float) $event->service_hours, 1) }} hrs</div></div></div>
        <div class="staff-list-item"><div><strong>Organizer</strong><div class="staff-muted">{{ $event->organizer ?? '—' }}</div></div></div>
        <div class="staff-list-item"><div><strong>Visibility</strong><div class="staff-muted">Published to all scholars in User / Events</div></div></div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header"><h2>Participation</h2></div>
        <div class="staff-stat-body">
            <div class="value">{{ $event->registrations_count ?? 0 }}</div>
            <div class="sub">Registered scholars</div>
        </div>
        <p class="staff-muted" style="margin-top:16px">Scholars who clicked Attend but did not check in after the event ends are marked Failed to Check In and receive 0 service hours.</p>
    </div>
</div>

@if(isset($participants))
<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Registered Scholars</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Hours</th>
                    <th>Photo</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($participants as $participant)
                    <tr>
                        <td>
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($participant['user']?->full_name ?? 'S', 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $participant['user']?->full_name ?? '—' }}</strong>
                                    <small>{{ $participant['user']?->scholar_id }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $participant['attendance']?->check_in?->format('g:i A') ?? '—' }}</td>
                        <td>{{ $participant['attendance']?->check_out?->format('g:i A') ?? '—' }}</td>
                        <td>{{ $participant['attendance']?->hoursLabel() ?? '0.00 hrs' }}</td>
                        <td>
                            @include('partials.staff-attendance-photo', [
                                'attendance' => $participant['attendance'],
                                'photoUrl' => $participant['attendance']?->hasPhoto() ? route('staff.attendances.photo', $participant['attendance']) : null,
                            ])
                        </td>
                        <td>
                            @php
                                $statusClass = match($participant['status']) {
                                    'approved', 'confirmed' => 'green',
                                    'rejected', 'failed_to_check_in' => 'red',
                                    default => 'orange',
                                };
                            @endphp
                            <span class="staff-badge {{ $statusClass }}">{{ $participant['status_label'] }}</span>
                        </td>
                        <td>
                            @if($participant['attendance'] && $participant['status'] !== 'failed_to_check_in')
                                @include('partials.staff-attendance-actions', ['attendance' => $participant['attendance']])
                            @else
                                <span class="staff-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No scholars have registered for this event yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@push('styles')
<style>.staff-muted{color:#6b7280;font-size:13px}</style>
@endpush
