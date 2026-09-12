@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')
@include('partials.admin-scope-banner')

<form method="GET" class="staff-filter-bar">
    @include('partials.admin-scope-fields')
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search events...">
    </div>
    <button type="submit" class="staff-btn staff-btn-primary">Search</button>
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Scholar Program</th>
                    <th>Program Type</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Service Hours</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td><strong>{{ $event->title }}</strong></td>
                        <td>{{ $event->scholarshipProgram?->programLabel() ?? '—' }}</td>
                        <td>{{ $event->scholarshipProgram?->programTypeLabel() ?? '—' }}</td>
                        <td>{{ $event->starts_at?->format('M j, Y g:i A') ?? '—' }}</td>
                        <td><span class="staff-badge {{ $event->scheduleBadgeClass() }}">{{ $event->scheduleLabel() }}</span></td>
                        <td>{{ $event->service_hours ?? 0 }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No events found for this scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $events->links() }}</div>
</div>

@endsection
