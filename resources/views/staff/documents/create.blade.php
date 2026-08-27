@extends('layouts.staff')

@section('page-content')

<div class="staff-card" style="max-width:760px">
    <div class="staff-card-header">
        <h2>Add Required Document</h2>
        <a href="{{ route('staff.documents') }}" class="staff-card-link">&larr; Back to Documents</a>
    </div>

    <p class="staff-muted" style="margin-bottom:20px">
        Scholars in your assigned location will see this as a required submission on their Documents page. Clicking the card later shows everyone who attached this file.
    </p>

    <form method="POST" action="{{ route('staff.documents.store') }}">
        @csrf

        <div class="staff-form-group">
            <label for="name">Document Name *</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Birth Certificate">
            @error('name')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="staff-form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Tell scholars what to upload and any special instructions...">{{ old('description') }}</textarea>
            @error('description')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="staff-form-group">
            <label class="staff-check-label">
                <input type="hidden" name="required" value="0">
                <input type="checkbox" name="required" value="1" @checked(old('required', true))>
                Scholars must submit this document
            </label>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px">
            <a href="{{ route('staff.documents') }}" class="staff-btn">Cancel</a>
            <button type="submit" class="staff-btn staff-btn-primary">Post Required Document</button>
        </div>
    </form>
</div>

@endsection

@push('styles')
<style>
.staff-muted{color:#6b7280;font-size:13px;margin:0}
.staff-form-group textarea{width:100%;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;resize:vertical}
.staff-field-error{color:#dc2626;font-size:12px;margin-top:4px}
.staff-check-label{display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer}
</style>
@endpush
