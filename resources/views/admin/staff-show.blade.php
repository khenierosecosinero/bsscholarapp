@extends('layouts.admin')

@section('page-content')

@php
    $backQuery = array_filter([
        'region' => request('region') ?: null,
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
        <p class="staff-muted admin-scholar-profile-note">View-only information from Scholar Staff registration.</p>
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

    <a href="{{ route('admin.staff', $backQuery) }}" class="staff-card-link admin-scholar-back">&larr; Back to scholar staff list</a>
</div>

@endsection
