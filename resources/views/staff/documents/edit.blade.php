@extends('layouts.staff')

@section('page-content')

<div class="staff-card" style="max-width:760px">
    <div class="staff-card-header">
        <h2>Edit {{ $documentType->name }}</h2>
        <a href="{{ route('staff.documents.show', $documentType) }}" class="staff-card-link">&larr; Back</a>
    </div>

    <form method="POST" action="{{ route('staff.documents.update', $documentType) }}">
        @csrf
        @method('PUT')

        <div class="staff-form-group">
            <label for="name">Document Name *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $documentType->name) }}" required>
            @error('name')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="staff-form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4">{{ old('description', $documentType->description) }}</textarea>
            @error('description')<div class="staff-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="staff-form-group">
            <label class="staff-check-label">
                <input type="hidden" name="required" value="0">
                <input type="checkbox" name="required" value="1" @checked(old('required', $documentType->required))>
                Scholars must submit this document
            </label>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px">
            <a href="{{ route('staff.documents.show', $documentType) }}" class="staff-btn">Cancel</a>
            <button type="submit" class="staff-btn staff-btn-primary">Save Changes</button>
        </div>
    </form>
</div>

@endsection

@push('styles')
<style>
.staff-form-group textarea{width:100%;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;resize:vertical}
.staff-field-error{color:#dc2626;font-size:12px;margin-top:4px}
.staff-check-label{display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer}
</style>
@endpush
