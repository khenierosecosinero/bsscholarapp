@extends('layouts.staff')

@section('page-content')

<div class="staff-card" style="max-width:760px">
    <div class="staff-card-header">
        <h2>Add School/University</h2>
        <a href="{{ route('staff.settings') }}" class="staff-card-link">&larr; Back to Settings</a>
    </div>

    <p class="staff-muted" style="margin-bottom:20px">
        This name will appear in the School/University dropdown when scholars register or update their profile under {{ $staff->scholarshipClubName() }}.
    </p>

    <form method="POST" action="{{ route('staff.settings.schools.store') }}">
        @csrf

        <div class="staff-form-group">
            <label for="name">School/University Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="School/University">
            @error('name')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px">
            <a href="{{ route('staff.settings') }}" class="staff-btn">Cancel</a>
            <button type="submit" class="staff-btn staff-btn-primary">Add School/University</button>
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
