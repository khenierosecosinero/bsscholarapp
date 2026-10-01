@extends('layouts.admin')

@section('page-content')

@php
    $eventsListQuery = array_filter([
        'region' => $selectedRegion ?: null,
        'location' => $locationKey ?? 'all',
        'club' => $selectedClubId ?: null,
        'search' => filled($search ?? '') ? $search : null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

@include('partials.admin-events-filter')

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Scholarship Club</th>
                    <th>Participants</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Service Hours</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td data-label="Event"><a href="{{ route('admin.events.show', array_merge(['event' => $event], $eventsListQuery)) }}" class="admin-event-name-link" aria-label="View scholars who attended {{ $event->title }}">{{ $event->title }}</a></td>
                        <td data-label="Scholarship Club">{{ $event->directoryClubLabel($selectedClubId ?? null) }}</td>
                        <td data-label="Participants">{{ $event->verifiedParticipantCount() }}</td>
                        <td data-label="Schedule">{{ $event->starts_at?->format('M j, Y g:i A') ?? '—' }}</td>
                        <td data-label="Status"><span class="staff-badge {{ $event->scheduleBadgeClass() }}">{{ $event->scheduleLabel() }}</span></td>
                        <td data-label="Service Hours">{{ $event->service_hours ?? 0 }}</td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="6">No events found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $events->links() }}</div>
</div>

@endsection
