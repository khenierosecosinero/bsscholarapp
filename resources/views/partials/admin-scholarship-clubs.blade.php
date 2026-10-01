@php
    $clubs = $scopedClubs ?? collect();
    $hasLocation = ! empty(($selectedAddress['province'] ?? null));
@endphp

<div class="admin-scholarship-clubs" data-admin-clubs>
    <div class="admin-scholarship-clubs-label">Scholarship Clubs</div>
    @if($clubs->isNotEmpty())
        <ul class="admin-scholarship-clubs-list">
            @foreach($clubs as $club)
                <li>
                    <strong>{{ $club->name }}</strong>
                    @if($club->addressLine())
                        <span class="staff-muted">{{ $club->addressLine() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p class="staff-muted admin-scholarship-clubs-empty">
            @if($hasLocation)
                No registered Scholarship Club found
            @else
                Select a Province to view registered Scholarship Clubs.
            @endif
        </p>
    @endif
</div>
