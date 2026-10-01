@extends('layouts.staff')

@section('page-content')

<div class="staff-card" style="max-width:760px">
    <div class="staff-card-header">
        <h2>Edit School/University</h2>
        <a href="{{ route('staff.settings') }}" class="staff-card-link">&larr; Back to Settings</a>
    </div>

    <p class="staff-muted" style="margin-bottom:20px">
        Scholars already linked to this School/University will see the updated name.
    </p>

    <form method="POST" action="{{ route('staff.settings.schools.update', $school) }}">
        @csrf
        @method('PUT')

        <div class="staff-form-group">
            <label for="name">School/University Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $school->name) }}" required maxlength="255">
            @error('name')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px">
            <a href="{{ route('staff.settings') }}" class="staff-btn">Cancel</a>
            <button type="submit" class="staff-btn staff-btn-primary">Save Changes</button>
        </div>
    </form>
</div>

@endsection

@push('styles')
<style>
.staff-muted{color:#6b7280;font-size:13px;margin:0}
.staff-field-error{color:#dc2626;font-size:12px;margin-top:4px}
</style>
@endpush
