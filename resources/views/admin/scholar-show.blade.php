@extends('layouts.admin')

@section('page-content')

@php
    $backQuery = array_filter([
        'location' => (($locationKey ?? 'all') !== 'all') ? $locationKey : null,
        'program_type' => (($programType ?? 'all') !== 'all') ? $programType : null,
    ]);
    $value = fn (?string $text) => filled($text) ? $text : '—';
@endphp

<div class="admin-scholar-profile">
    <div class="staff-card admin-scholar-profile-header">
        <div class="staff-scholar-cell">
            <x-user-avatar :user="$scholar" class="staff-scholar-avatar admin-scholar-profile-avatar" />
            <div class="staff-scholar-meta">
                <strong>{{ $scholar->full_name }}</strong>
                <small>{{ $scholar->scholar_id }}</small>
                <span class="staff-badge {{ $scholar->status === 'approved' ? 'green' : ($scholar->status === 'pending' ? 'orange' : 'red') }}">{{ ucfirst($scholar->status) }}</span>
            </div>
        </div>
        <p class="staff-muted admin-scholar-profile-note">View-only personal information from registration and profile setup.</p>
    </div>

    <div class="admin-scholar-profile-grid">
        <div class="staff-card">
            <div class="staff-card-header"><h2>Personal Information</h2></div>
            <div class="admin-scholar-fields">
                <div class="admin-scholar-field">
                    <span>Full Name</span>
                    <strong>{{ $value($scholar->full_name) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Scholar ID</span>
                    <strong>{{ $value($scholar->scholar_id) }}</strong>
                </div>
                <div class="admin-scholar-field admin-scholar-field-wide">
                    <span>Login Email</span>
                    <strong>{{ $value($scholar->email) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Cellphone</span>
                    <strong>{{ $value($scholar->cellphone_number) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Date of Birth</span>
                    <strong>{{ $scholar->date_of_birth?->format('F j, Y') ?? '—' }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Municipality / City</span>
                    <strong>{{ $value($scholar->municipalityName()) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Province</span>
                    <strong>{{ $value($scholar->provinceName()) }}</strong>
                </div>
            </div>
        </div>

        <div class="staff-card">
            <div class="staff-card-header"><h2>Academic Information</h2></div>
            <div class="admin-scholar-fields">
                <div class="admin-scholar-field admin-scholar-field-wide">
                    <span>Scholarship Club</span>
                    <strong>{{ $value($scholar->scholarshipClubName()) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Scholarship Club Municipality / City</span>
                    <strong>{{ $value($scholar->scholarshipClubCity()) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Scholarship Club Province</span>
                    <strong>{{ $value($scholar->scholarshipClubProvince()) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Program Type</span>
                    <strong>{{ $value($scholar->scholarshipProgram?->programTypeLabel()) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>School / University</span>
                    <strong>{{ $value($scholar->school_university) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Course</span>
                    <strong>{{ $value($scholar->course_year_level) }}</strong>
                </div>
                <div class="admin-scholar-field">
                    <span>Year Level</span>
                    <strong>{{ $value($scholar->year_level) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header"><h2>Guardian Information</h2></div>
        <div class="admin-scholar-fields">
            <div class="admin-scholar-field">
                <span>Guardian Name</span>
                <strong>{{ $value($scholar->guardian_name) }}</strong>
            </div>
            <div class="admin-scholar-field">
                <span>Relationship</span>
                <strong>{{ $value($scholar->guardian_relationship) }}</strong>
            </div>
            <div class="admin-scholar-field">
                <span>Guardian Cellphone</span>
                <strong>{{ $value($scholar->guardian_cellphone) }}</strong>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.scholars', $backQuery) }}" class="staff-card-link admin-scholar-back">&larr; Back to scholars list</a>
</div>

@endsection
