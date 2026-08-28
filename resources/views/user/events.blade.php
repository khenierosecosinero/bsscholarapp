@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendance-photo.css') }}">
@endpush

@section('page-content')

<form method="GET" action="{{ route('user.events') }}" class="filter-bar">
    <div class="search-box">
        <span class="search-icon">&#128269;</span>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search events...">
    </div>
    <select name="status" onchange="this.form.submit()">
        <option value="upcoming" @selected(request('status', 'upcoming') === 'upcoming')>Active Events</option>
        <option value="all" @selected(request('status') === 'all')>All Events</option>
        <option value="confirmed" @selected(request('status') === 'confirmed')>Confirmed</option>
        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
        <option value="failed_to_check_in" @selected(request('status') === 'failed_to_check_in')>Failed to Check In</option>
        <option value="not_joined" @selected(request('status') === 'not_joined')>Not Joined</option>
    </select>
    @if($selected)<input type="hidden" name="event" value="{{ $selected['id'] }}">@endif
</form>

<section class="events-page-grid">
    <div class="events-list-col">
        <div class="card">
            <div class="card-header">EVENTS</div>
            <div class="event-list vertical">
                @forelse($events as $ev)
                    <a href="{{ route('user.events', array_merge(request()->only('search', 'status'), ['event' => $ev['id']])) }}" class="event-item {{ ($selected && $selected['id'] === $ev['id']) ? 'selected' : '' }}">
                        <div class="event-date"><span class="month">{{ $ev['month'] }}</span><span class="day">{{ $ev['day'] }}</span><span class="dow">{{ $ev['dow'] }}</span></div>
                        @if($ev['image_url'])<img class="event-thumb" src="{{ $ev['image_url'] }}" alt="">@endif
                        <div class="event-details">
                            <div class="event-title">{{ $ev['title'] }}</div>
                            <div class="event-meta">{{ $ev['time'] }} · {{ $ev['location'] }}</div>
                            <div class="event-footer"><span class="hours">Service Hours: {{ $ev['hours'] }}</span></div>
                        </div>
                        <div class="event-action">
                            <span class="badge {{ $ev['status_class'] }}">{{ $ev['status_label'] }}</span>
                        </div>
                    </a>
                @empty
                    <p class="muted center">No events found.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="event-detail-col">
        @if($selected)
            <div class="card event-detail">
                <a href="{{ route('user.events', request()->only('search', 'status')) }}" class="back-link">&lt; Back to Events</a>
                <span class="badge {{ $selected['status_class'] }} float-right">{{ $selected['status_label'] }}</span>

                <div class="event-hero">
                    @if($selected['image_url'])<img src="{{ $selected['image_url'] }}" alt="{{ $selected['title'] }}">@endif
                    <div class="event-hero-info">
                        <h2>{{ $selected['title'] }}</h2>
                        <div class="info-row"><span class="info-icon">&#128197;</span> {{ $selected['full_date'] }}</div>
                        <div class="info-row"><span class="info-icon">&#128336;</span> {{ $selected['time'] }}</div>
                        <div class="info-row"><span class="info-icon">&#128205;</span> {{ $selected['location'] }}</div>
                        <div class="info-row"><span class="info-icon">&#9201;</span> Service Hours: {{ $selected['hours'] }}</div>
                        @if($selected['organizer'])<div class="info-row"><span class="info-icon">&#128101;</span> Organized by: {{ $selected['organizer'] }}</div>@endif
                    </div>
                </div>

                @if($selected['description'])
                    <div class="about-section"><h3>About this event</h3><p>{{ $selected['description'] }}</p></div>
                @endif

                @if($selected['registration_status'] === 'not_joined' && !$selected['is_past'])
                    <form method="POST" action="{{ route('user.events.register', $selected['id']) }}">@csrf<button type="submit" class="btn full">Attend / Register</button></form>
                @endif

                @if($selected['failed_to_check_in'])
                    <div class="reminder-box failed">
                        <strong>Failed to Check In:</strong> You registered for this event but did not check in through the attendance system. No service hours were credited.
                    </div>
                    <div class="card inner-card">
                        <div class="card-header">Your Attendance Record <span class="badge failed-to-check-in">Failed to Check In</span></div>
                        <table class="table compact">
                            <thead><tr><th>Check In</th><th>Check Out</th><th>Total Hours</th><th>Status</th></tr></thead>
                            <tbody><tr>
                                <td>—</td>
                                <td>—</td>
                                <td>0 hrs</td>
                                <td>Failed to Check In</td>
                            </tr></tbody>
                        </table>
                    </div>
                @elseif($selected['registration_status'] === 'confirmed')
                    <div class="reminder-box"><strong>Reminder:</strong> Check in during the event, then attach a photo of your participation so Scholar Staff can verify your attendance.</div>
                    <div class="card inner-card">
                        <div class="card-header">Attendance</div>
                        @php $att = $selected['attendance']; @endphp
                        @if(!$att || !$att->check_in)
                            <form method="POST" action="{{ route('user.events.check-in', $selected['id']) }}">@csrf<button type="submit" class="btn full checkin-btn" {{ !$selected['can_check_in'] ? 'disabled' : '' }}>&#128247; Check In</button></form>
                            <p class="muted center small">You can only check in during the event.</p>
                        @else
                            @if(!$att->check_out)
                                <form method="POST" action="{{ route('user.events.check-out', $selected['id']) }}">@csrf<button type="submit" class="btn full">Check Out</button></form>
                            @endif
                            @include('partials.attendance-photo-upload', ['attendance' => $att, 'eventId' => $selected['id']])
                        @endif
                    </div>
                    <div class="card inner-card">
                        <div class="card-header">Your Attendance Record
                        @if($att && $att->status === 'pending' && $att->check_in)
                            <span class="badge pending">{{ $att->hasPhoto() && $att->check_out ? 'Pending Verification' : ($att->hasPhoto() ? 'Pending Check Out' : 'Photo Required') }}</span>
                        @elseif($att && $att->status === 'approved')<span class="badge participated">Participated</span>
                        @elseif($att && $att->status === 'rejected')<span class="badge rejected">Rejected</span>
                        @endif
                        </div>
                        <table class="table compact">
                            <thead><tr><th>Check In</th><th>Check Out</th><th>Total Hours</th><th>Status</th></tr></thead>
                            <tbody><tr>
                                <td>{{ $att?->check_in?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $att?->check_out?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $att ? $att->hoursLabel() : '—' }}</td>
                                <td>{{ $att ? ($att->status === 'approved' ? 'Participated' : $att->statusLabel()) : '—' }}</td>
                            </tr></tbody>
                        </table>
                        @if($att && $att->hasPhoto())
                            <div class="attendance-record-photo">
                                <span>Participation photo</span>
                                <a href="{{ route('user.attendances.photo', $att) }}" target="_blank" rel="noopener">View photo</a>
                            </div>
                        @endif
                        @if($att && $att->status === 'pending' && $att->isReadyForVerification() && auth()->user()->isAdmin())
                            <form method="POST" action="{{ route('user.attendances.approve', $att->id) }}" class="attendance-approve-form">
                                @csrf
                                <button type="submit" class="btn full">Confirm Participation</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        @else
            <div class="card"><p class="muted center">Select an event to view details.</p></div>
        @endif
    </div>
</section>

@endsection
