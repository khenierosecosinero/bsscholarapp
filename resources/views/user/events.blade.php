@extends('layouts.user')

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
                            <span class="badge {{ $ev['registration_status'] === 'confirmed' ? 'confirmed' : ($ev['registration_status'] === 'pending' ? 'pending' : 'not-joined') }}">{{ ucfirst(str_replace('_', ' ', $ev['registration_status'])) }}</span>
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
                <span class="badge {{ $selected['has_participated'] ? 'participated' : ($selected['registration_status'] === 'confirmed' ? 'confirmed' : ($selected['registration_status'] === 'pending' ? 'pending' : 'not-joined')) }} float-right">{{ $selected['has_participated'] ? 'Participated' : ucfirst(str_replace('_', ' ', $selected['registration_status'])) }}</span>

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

                @if($selected['registration_status'] === 'confirmed')
                    <div class="reminder-box"><strong>Reminder:</strong> Check in during the event to log your attendance.</div>
                    <div class="card inner-card">
                        <div class="card-header">Attendance</div>
                        @php $att = $selected['attendance']; @endphp
                        @if(!$att || !$att->check_in)
                            <form method="POST" action="{{ route('user.events.check-in', $selected['id']) }}">@csrf<button type="submit" class="btn full checkin-btn" {{ !$selected['can_check_in'] ? 'disabled' : '' }}>&#128247; Check In</button></form>
                            <p class="muted center small">You can only check in during the event.</p>
                        @elseif(!$att->check_out)
                            <form method="POST" action="{{ route('user.events.check-out', $selected['id']) }}">@csrf<button type="submit" class="btn full">Check Out</button></form>
                        @else
                            <p class="muted center">Attendance submitted.</p>
                        @endif
                    </div>
                    <div class="card inner-card">
                        <div class="card-header">Your Attendance Record @if($att && $att->status === 'pending')<span class="badge pending">Pending Verification</span>@elseif($att && $att->status === 'approved')<span class="badge participated">Participated</span>@endif</div>
                        <table class="table compact">
                            <thead><tr><th>Check In</th><th>Check Out</th><th>Total Hours</th><th>Status</th></tr></thead>
                            <tbody><tr>
                                <td>{{ $att?->check_in?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $att?->check_out?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $att?->hours_earned ? $att->hours_earned . ' hrs' : '—' }}</td>
                                <td>{{ $att ? ($att->status === 'approved' ? 'Participated' : ucfirst($att->status)) : '—' }}</td>
                            </tr></tbody>
                        </table>
                        @if($att && $att->status === 'pending' && auth()->user()->isAdmin())
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
