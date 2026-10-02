@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile-page.css') }}?v={{ filemtime(public_path('css/profile-page.css')) }}">
@endpush

@section('page-content')
@php
    $activeProfileTab = $activeProfileTab ?? 'profile-info';
    $profileTabUrl = fn (string $tab) => route('user.profile', ['tab' => $tab]);
@endphp
<div class="page-profile">

<div class="tabs profile-tabs" role="tablist">
    <a href="{{ $profileTabUrl('profile-info') }}" class="tab {{ $activeProfileTab === 'profile-info' ? 'active' : '' }}" data-tab="profile-info" role="tab" aria-selected="{{ $activeProfileTab === 'profile-info' ? 'true' : 'false' }}">Profile Information</a>
    <a href="{{ $profileTabUrl('academic-settings') }}#academic-settings" class="tab {{ $activeProfileTab === 'academic-settings' ? 'active' : '' }}" data-tab="academic-settings" role="tab" aria-selected="{{ $activeProfileTab === 'academic-settings' ? 'true' : 'false' }}">Academic Settings</a>
    <a href="{{ $profileTabUrl('account-settings') }}#account-settings" class="tab {{ $activeProfileTab === 'account-settings' ? 'active' : '' }}" data-tab="account-settings" role="tab" aria-selected="{{ $activeProfileTab === 'account-settings' ? 'true' : 'false' }}">Account Settings</a>
    <a href="{{ $profileTabUrl('security') }}#security" class="tab {{ $activeProfileTab === 'security' ? 'active' : '' }}" data-tab="security" role="tab" aria-selected="{{ $activeProfileTab === 'security' ? 'true' : 'false' }}">Security</a>
</div>

<section class="profile-layout">
    <div class="profile-main">
        <div class="tab-panel profile-info-panel {{ $activeProfileTab === 'profile-info' ? 'active' : '' }}" id="profile-info" @if($activeProfileTab !== 'profile-info') hidden @endif>
            <div class="card profile-section-card">
                @php
                    $profileInfoErrors = $errors->hasAny([
                        'full_name',
                        'city',
                        'cellphone_number',
                        'scholarship_club_school_id',
                        'course_year_level',
                        'year_level',
                        'date_of_birth',
                    ]);
                @endphp

                <div class="card-header profile-section-heading">
                    <span>PROFILE INFORMATION</span>
                    <button type="button" class="btn blue" data-profile-edit data-profile-edit-for="scholar-profile-form">Edit</button>
                </div>

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

                <form id="scholar-profile-form" method="POST" action="{{ route('user.profile.update') }}" class="profile-section-form" data-profile-edit-form data-start-editing="{{ $profileInfoErrors ? '1' : '0' }}">
                    @csrf @method('PUT')
                    <div class="form-grid profile-form-grid">
                        <div class="form-group"><label for="full_name">Full Name</label><input id="full_name" type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" required autocomplete="name" data-profile-editable data-saved-value="{{ $user->full_name }}"></div>
                        <div class="form-group"><label for="scholar_id">Scholar ID</label><input id="scholar_id" type="text" value="{{ $user->scholar_id }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group form-group-wide"><label for="email">Login Email</label><input id="email" type="email" value="{{ $user->email }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group">
                            <label for="city">City Address</label>
                            <select id="city" name="city" class="form-select" data-profile-editable data-saved-value="{{ $user->municipalityName() }}">
                                <option value="">Select municipality or city</option>
                                @foreach($municipalityOptions as $municipality)
                                    <option value="{{ $municipality }}" @selected(old('city', $user->municipalityName()) === $municipality)>{{ $municipality }}</option>
                                @endforeach
                            </select>
                            @error('city')
                                <small class="muted" style="color:#dc2626">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group form-group-wide"><label for="registered_program">Scholarship Club</label><input id="registered_program" type="text" value="{{ $user->scholarshipClubName() }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group"><label for="registered_club_city">Scholarship Club Municipality / City</label><input id="registered_club_city" type="text" value="{{ $user->scholarshipClubCity() ?? '—' }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group"><label for="registered_province">Scholarship Club Province</label><input id="registered_province" type="text" value="{{ $user->scholarshipClubProvince() ?? '—' }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group"><label for="cellphone_number">Cellphone</label><input id="cellphone_number" type="text" name="cellphone_number" value="{{ old('cellphone_number', $user->cellphone_number) }}" autocomplete="tel" data-profile-editable data-saved-value="{{ $user->cellphone_number }}"></div>
                        <div class="form-group">
                            <label for="scholarship_club_school_id">School</label>
                            <select id="scholarship_club_school_id" name="scholarship_club_school_id" class="form-select" data-profile-editable data-saved-value="{{ $user->scholarship_club_school_id }}">
                                <option value="">Select school</option>
                                @foreach($clubSchools ?? [] as $school)
                                    <option value="{{ $school->id }}" @selected((string) old('scholarship_club_school_id', $user->scholarship_club_school_id) === (string) $school->id)>{{ $school->name }}</option>
                                @endforeach
                            </select>
                            @if(($clubSchools ?? collect())->isEmpty())
                                <small class="muted">No schools have been added for your Scholarship Club yet.</small>
                            @else
                                <small class="muted">Choose from the schools added by Scholar Staff for your Scholarship Club.</small>
                            @endif
                            @error('scholarship_club_school_id')
                                <small class="muted" style="color:#dc2626">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="course_year_level">Course</label>
                            <input id="course_year_level" type="text" name="course_year_level" value="{{ old('course_year_level', $user->course_year_level) }}" maxlength="255" autocomplete="off" placeholder="Bachelor of Science in ..." data-profile-editable data-saved-value="{{ $user->course_year_level }}">
                            @error('course_year_level')
                                <small class="muted" style="color:#dc2626">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="year_level">Year Level</label>
                            <select id="year_level" name="year_level" class="form-select" data-profile-editable data-saved-value="{{ $user->year_level }}">
                                <option value="">Select year level</option>
                                @foreach($yearLevels ?? [] as $yearLevel)
                                    <option value="{{ $yearLevel }}" @selected(old('year_level', $user->year_level) === $yearLevel)>{{ $yearLevel }}</option>
                                @endforeach
                            </select>
                            @error('year_level')
                                <small class="muted" style="color:#dc2626">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group form-group-date form-group-wide">
                            <label for="date_of_birth">Date of Birth</label>
                            <div class="date-input-wrap">
                                <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}" autocomplete="bday" data-profile-editable data-saved-value="{{ $user->date_of_birth?->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-actions-right profile-edit-actions">
                        <button type="button" class="btn" data-profile-cancel hidden>Cancel</button>
                        <button type="submit" class="btn blue" data-profile-save hidden disabled>Save Changes</button>
                    </div>
                </form>
            </div>

            <div class="card profile-section-card">
                @php
                    $guardianErrors = $errors->hasAny(['guardian_name', 'guardian_relationship', 'guardian_cellphone']);
                @endphp
                <form method="POST" action="{{ route('user.profile.guardian') }}" class="profile-section-form" data-profile-edit-form data-start-editing="{{ $guardianErrors ? '1' : '0' }}">
                    @csrf @method('PUT')
                    <div class="card-header profile-section-heading">
                        <span>GUARDIAN INFORMATION</span>
                        <button type="button" class="btn blue" data-profile-edit>Edit</button>
                    </div>

                    <div class="profile-section-intro">
                        <p>Please provide your guardian's contact details for emergency purposes.</p>
                    </div>

                    <div class="form-grid profile-form-grid guardian-form-grid">
                        <div class="form-group"><label for="guardian_name">Guardian Name</label><input id="guardian_name" type="text" name="guardian_name" value="{{ old('guardian_name', $user->guardian_name) }}" data-profile-editable data-saved-value="{{ $user->guardian_name }}"></div>
                        <div class="form-group"><label for="guardian_relationship">Relationship</label><input id="guardian_relationship" type="text" name="guardian_relationship" value="{{ old('guardian_relationship', $user->guardian_relationship) }}" data-profile-editable data-saved-value="{{ $user->guardian_relationship }}"></div>
                        <div class="form-group"><label for="guardian_cellphone">Guardian Cellphone</label><input id="guardian_cellphone" type="text" name="guardian_cellphone" value="{{ old('guardian_cellphone', $user->guardian_cellphone) }}" autocomplete="tel" data-profile-editable data-saved-value="{{ $user->guardian_cellphone }}"></div>
                    </div>
                    <div class="form-actions-right profile-edit-actions">
                        <button type="button" class="btn" data-profile-cancel hidden>Cancel</button>
                        <button type="submit" class="btn blue" data-profile-save hidden disabled>Save Guardian Info</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-panel {{ $activeProfileTab === 'academic-settings' ? 'active' : '' }}" id="academic-settings" @if($activeProfileTab !== 'academic-settings') hidden @endif>
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

        <div class="tab-panel {{ $activeProfileTab === 'account-settings' ? 'active' : '' }}" id="account-settings" @if($activeProfileTab !== 'account-settings') hidden @endif>
            <div class="card">
                <div class="card-header">LOGIN CREDENTIALS</div>
                <p class="muted">For your security, your login email, scholar ID, and password are set when you register and cannot be edited from this page.</p>
                <div class="form-grid">
                    <div class="form-group"><label>Login Email</label><input type="email" value="{{ $user->email }}" readonly disabled></div>
                    <div class="form-group"><label>Scholar ID</label><input type="text" value="{{ $user->scholar_id }}" readonly disabled></div>
                </div>
                <p class="muted small">To change your password, go to the <a href="{{ $profileTabUrl('security') }}#security" class="link tab-trigger" data-tab="security">Security</a> tab. To remove your account entirely, use the Danger Zone on the right.</p>
            </div>
        </div>

        <div class="tab-panel {{ $activeProfileTab === 'security' ? 'active' : '' }}" id="security" @if($activeProfileTab !== 'security') hidden @endif>
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
                <a href="{{ $profileTabUrl('academic-settings') }}#academic-settings" class="shortcut-item" data-tab="academic-settings" data-no-loading="true">
                    <span class="shortcut-icon" aria-hidden="true">&#128218;</span>
                    <span class="shortcut-label">Academic Settings</span>
                    <span class="shortcut-arrow" aria-hidden="true">&#9654;</span>
                </a>
                <a href="{{ $profileTabUrl('security') }}#security" class="shortcut-item" data-tab="security" data-no-loading="true">
                    <span class="shortcut-icon" aria-hidden="true">&#128274;</span>
                    <span class="shortcut-label">Change Password</span>
                    <span class="shortcut-arrow" aria-hidden="true">&#9654;</span>
                </a>
                <a href="{{ $profileTabUrl('account-settings') }}#account-settings" class="shortcut-item" data-tab="account-settings" data-no-loading="true">
                    <span class="shortcut-icon" aria-hidden="true">&#128100;</span>
                    <span class="shortcut-label">View Login Credentials</span>
                    <span class="shortcut-arrow" aria-hidden="true">&#9654;</span>
                </a>
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

@push('scripts')
<script>
(function () {
    const root = document.querySelector('.page-profile');
    if (!root || root.dataset.tabsReady === '1') return;
    root.dataset.tabsReady = '1';

    const allowed = ['profile-info', 'academic-settings', 'account-settings', 'security'];

    const activateTab = (id, scroll) => {
        if (!allowed.includes(id)) return false;

        root.querySelectorAll('.profile-tabs .tab').forEach((tab) => {
            const isActive = tab.dataset.tab === id;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        root.querySelectorAll('.tab-panel').forEach((panel) => {
            const isActive = panel.id === id;
            panel.hidden = !isActive;
            panel.classList.toggle('active', isActive);
        });

        const url = new URL(window.location.href);
        if (id === 'profile-info') {
            url.searchParams.delete('tab');
            url.hash = '';
        } else {
            url.searchParams.set('tab', id);
            url.hash = id;
        }
        history.replaceState(null, '', url);

        if (scroll) {
            const target = document.getElementById(id) || root.querySelector('.profile-tabs');
            requestAnimationFrame(() => {
                target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        return true;
    };

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-tab]');
        if (!trigger || !root.contains(trigger)) return;
        const id = trigger.dataset.tab;
        if (!id || !allowed.includes(id)) return;
        event.preventDefault();
        activateTab(id, trigger.classList.contains('shortcut-item') || Boolean(trigger.closest('.shortcut-list')) || trigger.classList.contains('tab-trigger'));
    });

    const fromUrl = new URLSearchParams(window.location.search).get('tab') || window.location.hash.replace('#', '');
    if (fromUrl && allowed.includes(fromUrl)) {
        activateTab(fromUrl, fromUrl !== 'profile-info');
    }
})();
</script>
@endpush
