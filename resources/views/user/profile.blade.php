@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile-page.css') }}?v={{ filemtime(public_path('css/profile-page.css')) }}">
@endpush

@section('page-content')
<div class="page-profile">

<div class="tabs profile-tabs" role="tablist">
    <button type="button" class="tab active" data-tab="profile-info">Profile Information</button>
    <button type="button" class="tab" data-tab="academic-settings">Academic Settings</button>
    <button type="button" class="tab" data-tab="account-settings">Account Settings</button>
    <button type="button" class="tab" data-tab="security">Security</button>
</div>

<section class="profile-layout">
    <div class="profile-main">
        <div class="tab-panel active profile-info-panel" id="profile-info">
            <div class="card profile-section-card">
                <div class="card-header">PROFILE INFORMATION</div>

                <div class="profile-header">
                    <div class="profile-avatar-wrap">
                        <x-user-avatar :user="$user" class="profile-avatar" />
                        <form method="POST" action="{{ route('user.profile.avatar') }}" enctype="multipart/form-data" class="profile-avatar-form">
                            @csrf
                            <label class="avatar-edit" for="profile-avatar-input" title="Change/Upload Profile Photo">
                                <span class="sr-only">Change or upload profile photo</span>
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path fill="currentColor" d="M9 3 7.17 5H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3.17L15 3H9zm3 15a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2.2A2.8 2.8 0 1 0 12 8.2a2.8 2.8 0 0 0 0 5.6z"/>
                                </svg>
                            </label>
                            <input
                                id="profile-avatar-input"
                                type="file"
                                name="avatar"
                                accept="image/jpeg,image/png,.jpg,.jpeg,.png"
                                hidden
                                onchange="this.form.submit()"
                            >
                        </form>
                    </div>
                    <div class="profile-header-body">
                        <h2>{{ $user->full_name }}</h2>
                        <span class="badge confirmed">{{ ucfirst($user->status ?? 'Active') }} Scholar</span>
                        <span class="muted">Scholar ID: {{ $user->scholar_id }}</span>
                        @error('avatar')
                            <span class="muted" style="color:#dc2626">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <form method="POST" action="{{ route('user.profile.update') }}" class="profile-section-form">
                    @csrf @method('PUT')
                    <div class="form-grid profile-form-grid">
                        <div class="form-group"><label for="full_name">Full Name</label><input id="full_name" type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" required autocomplete="name"></div>
                        <div class="form-group"><label for="scholar_id">Scholar ID</label><input id="scholar_id" type="text" value="{{ $user->scholar_id }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group form-group-wide"><label for="email">Login Email</label><input id="email" type="email" value="{{ $user->email }}" readonly disabled aria-readonly="true"><small class="muted">Your login email cannot be changed after registration.</small></div>
                        <div class="form-group"><label for="registered_municipality">Municipality / City</label><input id="registered_municipality" type="text" value="{{ $user->municipalityName() ?? '—' }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group"><label for="registered_province">Province</label><input id="registered_province" type="text" value="{{ $user->provinceName() ?? '—' }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group form-group-wide"><label for="registered_program">Scholarship Program</label><input id="registered_program" type="text" value="{{ $user->scholarshipProgram?->programLabel() ?? '—' }}" readonly disabled aria-readonly="true"><small class="muted">Your scholarship program is determined by your registered location and cannot be changed.</small></div>
                        <div class="form-group"><label for="cellphone_number">Cellphone</label><input id="cellphone_number" type="text" name="cellphone_number" value="{{ old('cellphone_number', $user->cellphone_number) }}" autocomplete="tel"></div>
                        <div class="form-group"><label for="school_university">School</label><input id="school_university" type="text" name="school_university" value="{{ old('school_university', $user->school_university) }}"></div>
                        <div class="form-group"><label for="course_year_level">Course</label><input id="course_year_level" type="text" name="course_year_level" value="{{ old('course_year_level', $user->course_year_level) }}"></div>
                        <div class="form-group"><label for="year_level">Year Level</label><input id="year_level" type="text" name="year_level" value="{{ old('year_level', $user->year_level) }}"></div>
                        <div class="form-group"><label for="date_of_birth">Date of Birth</label><input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}"></div>
                    </div>
                    <div class="form-actions-right"><button type="submit" class="btn blue">Save Changes</button></div>
                </form>
            </div>

            <div class="card profile-section-card">
                <div class="card-header">GUARDIAN INFORMATION</div>

                <div class="profile-section-intro">
                    <p>Please provide your guardian's contact details for emergency purposes.</p>
                </div>

                <form method="POST" action="{{ route('user.profile.guardian') }}" class="profile-section-form">
                    @csrf @method('PUT')
                    <div class="form-grid profile-form-grid guardian-form-grid">
                        <div class="form-group"><label for="guardian_name">Guardian Name</label><input id="guardian_name" type="text" name="guardian_name" value="{{ old('guardian_name', $user->guardian_name) }}"></div>
                        <div class="form-group"><label for="guardian_relationship">Relationship</label><input id="guardian_relationship" type="text" name="guardian_relationship" value="{{ old('guardian_relationship', $user->guardian_relationship) }}"></div>
                        <div class="form-group"><label for="guardian_cellphone">Guardian Cellphone</label><input id="guardian_cellphone" type="text" name="guardian_cellphone" value="{{ old('guardian_cellphone', $user->guardian_cellphone) }}" autocomplete="tel"></div>
                    </div>
                    <div class="form-actions-right"><button type="submit" class="btn blue">Save Guardian Info</button></div>
                </form>
            </div>
        </div>

        <div class="tab-panel" id="academic-settings" hidden>
            <div class="card">
                <div class="card-header">YOUR ACADEMIC PERIOD</div>
                <p class="muted small">Select the semester and academic year used to calculate your service hours, progress, and related records throughout the app.</p>
                @php
                    $userYear = old('year_start', $user->academic_year_start ?? $globalAcademicSettings->year_start);
                    $userSemester = old('semester', $user->semester ?? $globalAcademicSettings->semester);
                @endphp
                <form method="POST" action="{{ route('user.profile.academic') }}">
                    @csrf @method('PUT')
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="year_start">Academic Year</label>
                            <select id="year_start" name="year_start" class="form-select" required>
                                @foreach($academicYearOptions as $year => $label)
                                    <option value="{{ $year }}" {{ (int) $userYear === (int) $year ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="semester">Current Semester</label>
                            <select id="semester" name="semester" class="form-select" required>
                                @foreach($semesterOptions as $option)
                                    <option value="{{ $option }}" {{ $userSemester === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-actions-right"><button type="submit" class="btn blue">Save Academic Period</button></div>
                </form>
            </div>

            @if($user->isAdmin())
                <div class="card" style="margin-top:16px">
                    <div class="card-header">SYSTEM-WIDE ACADEMIC PERIOD (ADMIN)</div>
                    <p class="muted small">Set the default academic year and semester for the entire system. New attendance records will be tagged with this period.</p>
                    <form method="POST" action="{{ route('user.profile.academic.global') }}">
                        @csrf @method('PUT')
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="global_year_start">Default Academic Year</label>
                                <select id="global_year_start" name="year_start" class="form-select" required>
                                    @foreach($academicYearOptions as $year => $label)
                                        <option value="{{ $year }}" {{ (int) $globalAcademicSettings->year_start === (int) $year ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="global_semester">Default Semester</label>
                                <select id="global_semester" name="semester" class="form-select" required>
                                    @foreach($semesterOptions as $option)
                                        <option value="{{ $option }}" {{ $globalAcademicSettings->semester === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-actions-right"><button type="submit" class="btn blue">Update System Period</button></div>
                    </form>
                </div>
            @endif
        </div>

        <div class="tab-panel" id="account-settings" hidden>
            <div class="card">
                <div class="card-header">LOGIN CREDENTIALS</div>
                <p class="muted">For your security, your login email, scholar ID, and password are set when you register and cannot be edited from this page.</p>
                <div class="form-grid">
                    <div class="form-group"><label>Login Email</label><input type="email" value="{{ $user->email }}" readonly disabled></div>
                    <div class="form-group"><label>Scholar ID</label><input type="text" value="{{ $user->scholar_id }}" readonly disabled></div>
                </div>
                <p class="muted small">To change your password, go to the <button type="button" class="link tab-trigger" data-tab="security">Security</button> tab. To remove your account entirely, use the Danger Zone on the right.</p>
            </div>
        </div>

        <div class="tab-panel" id="security" hidden>
            <div class="card">
                <div class="card-header">CHANGE PASSWORD</div>
                <p class="muted small">You must enter your current password to set a new one.</p>
                <form method="POST" action="{{ route('user.profile.password') }}">
                    @csrf @method('PUT')
                    <div class="form-grid">
                        <div class="form-group"><label for="current_password">Current Password</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password"></div>
                        <div class="form-group"><label for="password">New Password</label><input id="password" type="password" name="password" required autocomplete="new-password" minlength="8"></div>
                        <div class="form-group"><label for="password_confirmation">Confirm New Password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" minlength="8"></div>
                    </div>
                    <div class="form-actions-right"><button type="submit" class="btn blue">Change Password</button></div>
                </form>
            </div>
        </div>
    </div>

    <aside class="profile-sidebar">
        <div class="card">
            <div class="card-header">ACCOUNT SUMMARY</div>
            <div class="summary-rows">
                <div class="summary-row"><span class="summary-icon green">&#10003;</span> Account Status <span class="badge confirmed">{{ ucfirst($user->status ?? 'Approved') }}</span></div>
                <div class="summary-row"><span class="summary-icon blue">&#128197;</span> Member Since <strong>{{ $user->created_at->format('M Y') }}</strong></div>
                <div class="summary-row"><span class="summary-icon purple">&#127979;</span> Academic Year <strong>{{ $semesterInfo['academic_year_short'] }}</strong></div>
                <div class="summary-row"><span class="summary-icon orange">&#128218;</span> Current Semester <strong>{{ $semesterInfo['semester'] }}</strong></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">SECURITY SHORTCUTS</div>
            <div class="shortcut-list">
                <button type="button" class="shortcut-item tab-trigger" data-tab="academic-settings"><span>&#128218;</span> Academic Settings <span class="arrow">&#9654;</span></button>
                <button type="button" class="shortcut-item tab-trigger" data-tab="security"><span>&#128274;</span> Change Password <span class="arrow">&#9654;</span></button>
                <button type="button" class="shortcut-item tab-trigger" data-tab="account-settings"><span>&#128100;</span> View Login Credentials <span class="arrow">&#9654;</span></button>
            </div>
        </div>

        <div class="card danger-zone">
            <div class="card-header red">&#128465; DANGER ZONE</div>
            <p class="muted small">Deleting your account permanently removes your login credentials and all associated data.</p>
            <form method="POST" action="{{ route('user.profile.destroy') }}" id="delete-account-form">
                @csrf @method('DELETE')
                <div class="form-group"><label for="delete_password">Confirm Password</label><input id="delete_password" type="password" name="password" required placeholder="Enter password to confirm" autocomplete="current-password"></div>
                <button type="button" class="btn danger" id="delete-account-trigger">Delete My Account</button>
            </form>
        </div>

        @include('partials.delete-account-modal')

        @include('partials.sidebar-help-card', [
            'helpText' => 'If you have questions about your profile or account settings, you can visit our Help Center.',
        ])
    </aside>
</section>

</div>

@endsection
