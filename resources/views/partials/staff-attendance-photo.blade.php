@if($attendance?->hasPhoto())
    <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="staff-attendance-photo-link" title="View participation photo">
        <img src="{{ $photoUrl }}" alt="Participation photo" class="staff-attendance-photo">
    </a>
@else
    <span class="staff-muted">No photo</span>
@endif
