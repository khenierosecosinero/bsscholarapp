@extends('layouts.staff')

@section('page-content')

<div class="staff-card" style="max-width:760px">
    <div class="staff-card-header">
        <h2>Create New Event</h2>
        <a href="{{ route('staff.events') }}" class="staff-card-link">&larr; Back to Events</a>
    </div>

    <p class="staff-muted" style="margin-bottom:20px">
        The start and end date you set here is saved immediately and shown on the same day in the scholar Events page and Calendar of Activities.
    </p>

    <form method="POST" action="{{ route('staff.events.store') }}" class="staff-settings-grid" enctype="multipart/form-data">
        @csrf

        <div class="staff-form-group">
            <label for="title">Event Title *</label>
            <input type="text" id="title" name="title" value="{{ old('title') }}" required placeholder="e.g. Tree Planting Activity">
            @error('title')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="staff-form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Describe the event, requirements, and objectives...">{{ old('description') }}</textarea>
            @error('description')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="staff-form-grid">
            <div class="staff-form-group">
                <label for="location">Location *</label>
                <input type="text" id="location" name="location" value="{{ old('location') }}" required placeholder="e.g. Brgy. San Roque, Surigao City">
                @error('location')<div class="staff-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="staff-form-group">
                <label for="organizer">Organizer</label>
                <input type="text" id="organizer" name="organizer" value="{{ old('organizer', $staff->full_name) }}" placeholder="Organizing group or person">
                @error('organizer')<div class="staff-field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="staff-form-grid">
            <div class="staff-form-group">
                <label for="starts_at">Start Date &amp; Time *</label>
                <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at') }}" required>
                @error('starts_at')<div class="staff-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="staff-form-group">
                <label for="ends_at">End Date &amp; Time *</label>
                <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at') }}" required>
                @error('ends_at')<div class="staff-field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="staff-form-group">
            <label for="service_hours">Service Hours *</label>
            <input type="number" id="service_hours" name="service_hours" value="{{ old('service_hours', '4') }}" min="0" step="0.5" required>
            @error('service_hours')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="staff-form-group">
            <label for="image">Event Image</label>
            <div class="staff-event-image-drop" id="event-image-drop">
                <span class="staff-event-image-drop-icon" aria-hidden="true">&#128247;</span>
                <span>Drag and drop an image here, or choose a file</span>
                <small>JPG, JPEG, or PNG, up to 5MB</small>
                <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
            </div>
            @error('image')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px">
            <a href="{{ route('staff.events') }}" class="staff-btn">Cancel</a>
            <button type="submit" class="staff-btn staff-btn-primary">Publish Event</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var drop = document.getElementById('event-image-drop');
    var input = document.getElementById('image');
    if (!drop || !input) return;

    ['dragenter', 'dragover'].forEach(function (type) {
        drop.addEventListener(type, function (event) {
            event.preventDefault();
            drop.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (type) {
        drop.addEventListener(type, function (event) {
            event.preventDefault();
            drop.classList.remove('is-dragover');
        });
    });

    drop.addEventListener('drop', function (event) {
        var files = event.dataTransfer && event.dataTransfer.files;
        if (!files || !files.length) return;
        input.files = files;
    });
});
</script>

@endsection

@push('styles')
<style>
.staff-muted{color:#6b7280;font-size:13px;margin:0}
.staff-form-group textarea{width:100%;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;resize:vertical}
.staff-field-error{color:#dc2626;font-size:12px;margin-top:4px}
.staff-event-image-drop{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:6px;
    border:2px dashed #d1d5db;
    border-radius:12px;
    padding:20px 16px;
    text-align:center;
    background:#fff;
    font-size:14px;
    font-weight:600;
    color:#0f172a;
}
.staff-event-image-drop.is-dragover{border-color:#2563eb;background:#eff6ff}
.staff-event-image-drop-icon{font-size:22px;line-height:1}
.staff-event-image-drop small{font-weight:400;color:#6b7280;font-size:12px}
.staff-event-image-drop input[type="file"]{margin-top:8px;font-size:13px;font-weight:400;width:auto;padding:0;border:none}
</style>
@endpush
