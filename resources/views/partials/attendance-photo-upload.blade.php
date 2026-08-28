@php
    $hasPhoto = $attendance->hasPhoto();
    $photoUrl = $hasPhoto ? route('user.attendances.photo', $attendance) : null;
@endphp

@if($hasPhoto)
    <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="attendance-photo-link">
        <img src="{{ $photoUrl }}" alt="Your participation photo" class="attendance-photo-preview">
    </a>
    <p class="attendance-photo-note">Photo attached as proof of participation.</p>
@else
    <p class="attendance-photo-note"><strong>Photo required.</strong> Upload a photo showing you at this event so Scholar Staff can verify your attendance before approving service hours.</p>
@endif

@if($attendance->status === 'rejected' && $attendance->remarks)
    <p class="attendance-photo-note attendance-photo-rejected">{{ $attendance->remarks }}</p>
@endif

@if($attendance->canReplacePhoto())
    <form method="POST" action="{{ route('user.events.photo', $eventId) }}" enctype="multipart/form-data" class="attendance-photo-form">
        @csrf
        <label class="attendance-photo-drop">
            <span class="attendance-photo-drop-icon" aria-hidden="true">&#128247;</span>
            <span>{{ $hasPhoto ? 'Choose a new photo' : 'Choose a photo of your participation' }}</span>
            <small>JPG or PNG, up to 5MB</small>
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
        </label>
        <button type="submit" class="btn full">{{ $hasPhoto ? 'Replace Photo' : 'Attach Photo' }}</button>
    </form>
@elseif($attendance->check_out && $hasPhoto)
    <p class="muted center">Attendance submitted.</p>
@endif
