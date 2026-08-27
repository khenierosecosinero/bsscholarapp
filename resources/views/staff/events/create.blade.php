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

    <form method="POST" action="{{ route('staff.events.store') }}" class="staff-settings-grid">
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

        <div class="staff-form-grid">
            <div class="staff-form-group">
                <label for="service_hours">Service Hours *</label>
                <input type="number" id="service_hours" name="service_hours" value="{{ old('service_hours', '4') }}" min="0" step="0.5" required>
                @error('service_hours')<div class="staff-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="staff-form-group">
                <label for="status">Status *</label>
                <select id="status" name="status" required>
                    <option value="confirmed" @selected(old('status', 'confirmed') === 'confirmed')>Confirmed (visible on scholar calendars)</option>
                    <option value="upcoming" @selected(old('status') === 'upcoming')>Upcoming (visible on scholar calendars)</option>
                    <option value="pending" @selected(old('status') === 'pending')>Draft / pending (still shown on calendars)</option>
                </select>
                @error('status')<div class="staff-field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="staff-form-group">
            <label for="image_url">Event Image URL</label>
            <input type="url" id="image_url" name="image_url" value="{{ old('image_url') }}" placeholder="https://example.com/event-photo.jpg">
            @error('image_url')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px">
            <a href="{{ route('staff.events') }}" class="staff-btn">Cancel</a>
            <button type="submit" class="staff-btn staff-btn-primary">Publish Event</button>
        </div>
    </form>
</div>

@endsection

@push('styles')
<style>
.staff-muted{color:#6b7280;font-size:13px;margin:0}
.staff-form-group textarea{width:100%;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;resize:vertical}
.staff-field-error{color:#dc2626;font-size:12px;margin-top:4px}
</style>
@endpush
