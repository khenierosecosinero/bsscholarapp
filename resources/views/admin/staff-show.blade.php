@extends('layouts.admin')

@section('page-content')

@php
    $backQuery = array_filter([
        'location' => request('location', $locationKey ?? 'all'),
        'club' => request('club') ?: null,
        'search' => request('search') ?: null,
    ], fn ($value) => $value !== null && $value !== '');
    $value = fn (?string $text) => filled($text) ? $text : '—';
    $statusClass = $member->staffStatusBadgeClass();
@endphp

<div class="admin-scholar-profile">
    <div class="staff-card admin-scholar-profile-header">
        <div class="staff-scholar-cell">
            <x-user-avatar :user="$member" class="staff-scholar-avatar admin-scholar-profile-avatar" />
            <div class="staff-scholar-meta">
                <strong>{{ $member->full_name }}</strong>
                <small>{{ $value($member->contactNumber()) }}</small>
                <span class="staff-badge {{ $statusClass }}">{{ $member->staffStatusLabel() }}</span>
            </div>
        </div>
        <p class="staff-muted admin-scholar-profile-note">Registration details for this Scholar Staff account. Started Year can be updated by an administrator.</p>
        <div class="staff-scholar-profile-actions">
            @include('partials.account-access-actions', [
                'account' => $member,
                'blockUrl' => route('admin.staff.block', $member),
                'suspendUrl' => route('admin.staff.suspend', $member),
                'reactivateUrl' => route('admin.staff.reactivate', $member),
            ])
        </div>
    </div>

    <div class="admin-scholar-profile-grid">
        <div class="staff-card">
            <div class="staff-card-header"><h2>Staff Information</h2></div>
            <div class="admin-scholar-fields">
                <div class="admin-scholar-field">
                    <span>Full Name</span>
                    <strong>{{ $value($member->full_name) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Contact Number</span>
                    <strong>{{ $value($member->contactNumber()) }}</strong>
                </div>
                <div class="admin-scholar-field admin-scholar-field-wide">
                    <span>Email</span>
                    <strong>{{ $value($member->email) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Status</span>
                    <strong>{{ $member->staffStatusLabel() }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Started Year</span>
                    <strong>{{ $member->staffStartedYearLabel() ?? 'Not set' }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Date Registered</span>
                    <strong>{{ $member->created_at?->format('F j, Y g:i A') ?? '—' }}</strong>
                </div>
            </div>
        </div>

        <div class="staff-card">
            <div class="staff-card-header"><h2>Scholarship Club</h2></div>
            <div class="admin-scholar-fields">
                <div class="admin-scholar-field admin-scholar-field-wide">
                    <span>Scholarship Club</span>
                    <strong>{{ $value($member->scholarshipClubName()) }}</strong>
                </div>
                <div class="admin-scholar-field admin-scholar-field-wide">
                    <span>No./Street</span>
                    <strong>{{ $value($member->scholarshipClubStreet() ?: $member->streetName()) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Municipality / City</span>
                    <strong>{{ $value($member->scholarshipClubCity() ?: $member->city) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Province</span>
                    <strong>{{ $value($member->scholarshipClubProvince() ?: $member->province) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="staff-card" style="margin-top:16px">
        <div class="staff-card-header"><h2>Update Started Year</h2></div>
        <p class="staff-muted" style="margin:0 0 14px">
            Record the academic year when this Scholar Staff member started their role. Options come from Academic Years configured in Admin Settings.
        </p>
        <form method="POST" action="{{ route('admin.staff.started-year', $member) }}" class="staff-form-grid">
            @csrf
            @method('PUT')
            @foreach($backQuery as $key => $val)
                <input type="hidden" name="{{ $key }}" value="{{ $val }}">
            @endforeach
            <div class="staff-form-group">
                <label for="staff_started_year">Started Year</label>
                <select id="staff_started_year" name="staff_started_year" class="staff-select">
                    <option value="" @selected(! $member->staff_started_year)>Not set</option>
                    @foreach($staffStartedYearOptions ?? [] as $year => $label)
                        <option value="{{ $year }}" @selected((int) old('staff_started_year', $member->staff_started_year) === (int) $year)>AY {{ $label }}</option>
                    @endforeach
                </select>
                @error('staff_started_year')
                    <div class="staff-field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="staff-form-group" style="display:flex;align-items:flex-end">
                <button type="submit" class="staff-btn staff-btn-primary">Save Started Year</button>
            </div>
        </form>
    </div>

    <a href="{{ route('admin.staff', $backQuery) }}" class="staff-card-link admin-scholar-back">&larr; Back to scholar staff list</a>
</div>

@endsection
