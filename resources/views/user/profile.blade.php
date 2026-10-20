@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile-page.css') }}?v={{ filemtime(public_path('css/profile-page.css')) }}">
@endpush

@section('page-content')
@php
    $activeProfileTab = $activeProfileTab ?? 'profile-info';
    $profileTabUrl = fn (string $tab) => route('user.profile', ['tab' => $tab]);
    $accountPending = $user->isPendingApproval();
@endphp
<div class="page-profile">

@if($accountPending)
    @include('partials.bssa-alert', [
        'type' => 'warning',
        'title' => 'Pending Approval',
        'message' => 'Your account is pending approval. Profile details are view-only until Scholar Staff approves your registration.',
        'dismissible' => true,
        'autoDismiss' => false,
        'class' => 'bssa-alert--banner',
    ])
@endif

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
                        'street',
                        'province',
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
                    @unless($accountPending)
                        <button type="button" class="btn blue" data-profile-edit data-profile-edit-for="scholar-profile-form">Edit</button>
                    @endunless
                </div>

                <div class="profile-header">
                    <div class="profile-avatar-wrap">
                        <x-user-avatar :user="$user" class="profile-avatar" />
                        <form method="POST" action="{{ route('user.profile.avatar') }}" enctype="multipart/form-data" class="profile-avatar-form" @if($accountPending) hidden @endif>
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
                        <div class="form-group"><label for="full_name">Full Name</label><input id="full_name" type="text" value="{{ $user->full_name }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group"><label for="scholar_id">Scholar ID</label><input id="scholar_id" type="text" value="{{ $user->scholar_id }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group form-group-wide"><label for="email">Login Email</label><input id="email" type="email" value="{{ $user->email }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group form-group-wide" data-profile-address>
                            <label for="street">No./Street & Barangay</label>
                            <input id="street" type="text" name="street" value="{{ old('street', $user->streetName()) }}" maxlength="255" autocomplete="address-line1" placeholder="House number and street name" data-profile-editable data-saved-value="{{ $user->streetName() }}">
                            <small class="muted">House number and street name.</small>
                            @error('street')
                                <small class="muted" style="color:#dc2626">{{ $message }}</small>
                            @enderror
                            <script type="application/json" data-profile-municipalities>@json($municipalitiesByProvince ?? [])</script>
                        </div>
                        <div class="form-group">
                            <label for="province">Province</label>
                            <select id="province" name="province" class="form-select" data-profile-editable data-location-province data-saved-value="{{ $selectedProvince }}">
                                <option value="">Select province</option>
                                @foreach($provinceOptions ?? [] as $provinceOption)
                                    <option value="{{ $provinceOption }}" @selected((string) old('province', $selectedProvince) === (string) $provinceOption)>{{ $provinceOption }}</option>
                                @endforeach
                            </select>
                            @error('province')
                                <small class="muted" style="color:#dc2626">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="city">Municipality / City</label>
                            <select id="city" name="city" class="form-select" data-profile-editable data-location-city data-saved-value="{{ $user->municipalityName() }}">
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
                        <div class="form-group form-group-wide"><label for="registered_club_street">Scholarship Club No./Street</label><input id="registered_club_street" type="text" value="{{ $user->scholarshipClubStreet() ?: '—' }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group"><label for="registered_club_city">Scholarship Club Municipality / City</label><input id="registered_club_city" type="text" value="{{ $user->scholarshipClubCity() ?? '—' }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group"><label for="registered_province">Scholarship Club Province</label><input id="registered_province" type="text" value="{{ $user->scholarshipClubProvince() ?? '—' }}" readonly disabled aria-readonly="true"></div>
                        <div class="form-group">
                            <label for="cellphone_number">Cellphone Number</label>
                            <input id="cellphone_number" type="text" name="cellphone_number" value="{{ old('cellphone_number', $user->cellphone_number) }}" autocomplete="tel" inputmode="tel" placeholder="09XXXXXXXXX" data-profile-editable data-saved-value="{{ $user->cellphone_number }}">
                            @error('cellphone_number')
                                <small class="muted" style="color:#dc2626">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="scholarship_club_school_id">School/University</label>
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
                            @error('date_of_birth')
                                <small class="muted" style="color:#dc2626">{{ $message }}</small>
                            @enderror
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
                        @unless($accountPending)
                            <button type="button" class="btn blue" data-profile-edit>Edit</button>
                        @endunless
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
            @php
                $startYearOptions = $programStartYearOptions ?? $academicYearOptions ?? [];
                $savedStartYear = old('program_start_year', $user->program_start_year);
                $viewPeriod = $academicPeriod ?? $globalAcademicSettings;
                $selectedYearStart = (int) old('year_start', $viewPeriod->year_start);
                $selectedSemester = (string) old('semester', $viewPeriod->semester);
            @endphp

            <div class="card">
                <div class="card-header">ACADEMIC INFORMATION</div>
                <p class="muted small">These details come from your account. Scholarship Club assignment is controlled by the Administrator and Scholar Staff. Update your school, course, and year level from Profile.</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="academic-club">Scholarship Club</label>
                        <input id="academic-club" type="text" value="{{ filled($user->scholarshipClubName()) ? $user->scholarshipClubName() : 'Not on file' }}" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="academic-school">School/University</label>
                        <input id="academic-school" type="text" value="{{ filled($user->school_university) ? $user->school_university : 'Not on file' }}" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="academic-course">Course</label>
                        <input id="academic-course" type="text" value="{{ filled($user->course_year_level) ? $user->course_year_level : 'Not on file' }}" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="academic-year-level">Year Level</label>
                        <input id="academic-year-level" type="text" value="{{ filled($user->year_level) ? $user->year_level : 'Not on file' }}" readonly disabled>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top:16px">
                <div class="card-header">START ACADEMIC YEAR</div>
                <p class="muted small">This is the academic year when you originally began the scholarship. Year 1–Year 4 are calculated from this year and stay fixed. Changing the viewing Academic Year and Semester below does not change your Start Academic Year.</p>
                <form method="POST" action="{{ route('user.profile.academic') }}">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="program_start_year">Start Academic Year</label>
                            <select id="program_start_year" name="program_start_year" class="form-select" required>
                                <option value="" disabled @selected($savedStartYear === null || $savedStartYear === '')>Select start academic year</option>
                                @foreach($startYearOptions as $year => $label)
                                    <option value="{{ $year }}" @selected((int) $savedStartYear === (int) $year)>AY {{ $label }}</option>
                                @endforeach
                            </select>
                            @error('program_start_year')
                                <p class="bssa-field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="form-actions-right"><button type="submit" class="btn blue">Save Start Academic Year</button></div>
                </form>
            </div>

            <div class="card" style="margin-top:16px">
                <div class="card-header">CURRENT ACADEMIC PERIOD</div>
                <p class="muted small">Choose an academic year and semester to filter your service hours and records. The choice is saved to your account and restored the next time you sign in. It does not change your Start Academic Year, saved attendance, or the administrator's academic years.</p>
                <form method="POST" action="{{ route('academic.period.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="year_start">Academic Year</label>
                            <select id="year_start" name="year_start" class="form-select" required>
                                @foreach($academicYearOptions as $year => $label)
                                    <option value="{{ $year }}" @selected($selectedYearStart === (int) $year)>AY {{ $label }}</option>
                                @endforeach
                            </select>
                            @error('year_start')
                                <p class="bssa-field-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester" class="form-select" required>
                                @foreach($semesterOptions as $option)
                                    <option value="{{ $option }}" @selected($selectedSemester === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('semester')
                                <p class="bssa-field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="form-actions-right"><button type="submit" class="btn blue">Apply View</button></div>
                </form>
            </div>

            @if($user->isAdmin())
                <div class="card" style="margin-top:16px">
                    <div class="card-header">ACADEMIC YEARS</div>
                    <p class="muted small">Add and edit academic years from Admin Settings. The dropdown above only changes which year and semester you are viewing.</p>
                    <div class="form-actions-right"><a href="{{ route('admin.settings') }}" class="btn blue">Manage Academic Years</a></div>
                </div>
            @endif
        </div>

        <div class="tab-panel {{ $activeProfileTab === 'account-settings' ? 'active' : '' }}" id="account-settings" @if($activeProfileTab !== 'account-settings') hidden @endif>
            <div class="card">
                <div class="card-header">LOGIN CREDENTIALS</div>
                <p class="muted">These are the login details for the account you are signed in with. Your password is stored as a secure hash and is never shown.</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="login-email">Login Email</label>
                        @if(filled($user->email))
                            <input id="login-email" type="email" value="{{ $user->email }}" readonly disabled autocomplete="username">
                        @else
                            <p class="bssa-field-error">This account has no login email on file. Ask Scholar Staff to update your account before signing in with email.</p>
                        @endif
                    </div>
                    <div class="form-group">
                        <label for="login-scholar-id">Scholar ID</label>
                        @if(filled($user->scholar_id))
                            <input id="login-scholar-id" type="text" value="{{ $user->scholar_id }}" readonly disabled autocomplete="off">
                        @else
                            <p class="bssa-field-error">This account has no Scholar ID on file.</p>
                        @endif
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input id="login-password" type="text" value="Hidden" readonly disabled autocomplete="off" aria-describedby="login-password-note">
                        <p id="login-password-note" class="muted small">Your password, its hash, and your session token are never displayed. Use Change Password to replace it.</p>
                    </div>
                </div>
                <p class="muted small">To change your password, go to the <a href="{{ $profileTabUrl('security') }}#security" class="link tab-trigger" data-tab="security">Security</a> tab. To remove your account entirely, use the Danger Zone on the right.</p>
            </div>
        </div>

        <div class="tab-panel {{ $activeProfileTab === 'security' ? 'active' : '' }}" id="security" @if($activeProfileTab !== 'security') hidden @endif>
            <div class="card">
                <div class="card-header">CHANGE PASSWORD</div>
                <p class="muted small">Enter your current password, then a new password of at least 8 characters. The new password and confirmation must match.</p>
                <form method="POST" action="{{ route('user.profile.password') }}" id="change-password-form" autocomplete="off">
                    @csrf @method('PUT')
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                            @error('current_password')
                                <p class="bssa-field-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input id="password" type="password" name="password" required autocomplete="new-password" minlength="8">
                            @error('password')
                                <p class="bssa-field-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Confirm New Password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" minlength="8">
                        </div>
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
                <a href="{{ $profileTabUrl('academic-settings') }}#academic-settings" class="shortcut-item {{ $activeProfileTab === 'academic-settings' ? 'is-current' : '' }}" data-tab="academic-settings" data-no-loading="true" @if($activeProfileTab === 'academic-settings') aria-current="page" @endif>
                    <span class="shortcut-icon" aria-hidden="true">&#128218;</span>
                    <span class="shortcut-label">Academic Settings</span>
                    <span class="shortcut-arrow" aria-hidden="true">&#9654;</span>
                </a>
                <a href="{{ $profileTabUrl('security') }}#security" class="shortcut-item {{ $activeProfileTab === 'security' ? 'is-current' : '' }}" data-tab="security" data-no-loading="true" @if($activeProfileTab === 'security') aria-current="page" @endif>
                    <span class="shortcut-icon" aria-hidden="true">&#128274;</span>
                    <span class="shortcut-label">Change Password</span>
                    <span class="shortcut-arrow" aria-hidden="true">&#9654;</span>
                </a>
                <a href="{{ $profileTabUrl('account-settings') }}#account-settings" class="shortcut-item {{ $activeProfileTab === 'account-settings' ? 'is-current' : '' }}" data-tab="account-settings" data-no-loading="true" @if($activeProfileTab === 'account-settings') aria-current="page" @endif>
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

    const tabFromLocation = () => {
        const query = new URLSearchParams(window.location.search).get('tab') || '';
        const hash = window.location.hash.replace('#', '');
        const id = allowed.includes(query) ? query : (allowed.includes(hash) ? hash : 'profile-info');
        return id;
    };

    const activateTab = (id, scroll, historyMode) => {
        if (!allowed.includes(id)) return false;
        const current = root.querySelector('.tab-panel.active')?.id || 'profile-info';

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

        root.querySelectorAll('.shortcut-item[data-tab]').forEach((item) => {
            const isActive = item.dataset.tab === id;
            item.classList.toggle('is-current', isActive);
            if (isActive) item.setAttribute('aria-current', 'page');
            else item.removeAttribute('aria-current');
        });

        const url = new URL(window.location.href);
        if (id === 'profile-info') {
            url.searchParams.delete('tab');
            url.hash = '';
        } else {
            url.searchParams.set('tab', id);
            url.hash = id;
        }
        const next = url.pathname + url.search + url.hash;
        const now = window.location.pathname + window.location.search + window.location.hash;
        if (historyMode === 'push' && current !== id) {
            history.pushState({ profileTab: id }, '', next);
        } else if (historyMode !== 'none' && next !== now) {
            history.replaceState({ profileTab: id }, '', next);
        }

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
        activateTab(id, trigger.classList.contains('shortcut-item') || Boolean(trigger.closest('.shortcut-list')) || trigger.classList.contains('tab-trigger'), 'push');
    });

    window.addEventListener('popstate', () => {
        activateTab(tabFromLocation(), true, 'none');
    });

    activateTab(tabFromLocation(), tabFromLocation() !== 'profile-info', 'replace');

    const passwordForm = document.getElementById('change-password-form');
    const newPassword = document.getElementById('password');
    const confirmPassword = document.getElementById('password_confirmation');
    if (passwordForm && newPassword && confirmPassword) {
        const syncPasswordMatch = () => {
            if (confirmPassword.value !== '' && newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('New password and confirmation must match.');
            } else {
                confirmPassword.setCustomValidity('');
            }
        };
        newPassword.addEventListener('input', syncPasswordMatch);
        confirmPassword.addEventListener('input', syncPasswordMatch);
        passwordForm.addEventListener('submit', (event) => {
            syncPasswordMatch();
            if (!passwordForm.checkValidity()) {
                event.preventDefault();
                passwordForm.reportValidity();
            }
        });
    }

    const params = new URLSearchParams(window.location.search);
    if (params.get('updated') === '1') {
        params.delete('updated');
        const next = new URL(window.location.href);
        next.search = params.toString();
        history.replaceState(null, '', next);
    }
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-profile-address]');
    var form = document.getElementById('scholar-profile-form');
    if (!root || !form) {
        return;
    }

    var provinceSelect = form.querySelector('[data-location-province]');
    var citySelect = form.querySelector('[data-location-city]');
    var catalogEl = root.querySelector('[data-profile-municipalities]');
    var catalog = {};

    try {
        catalog = JSON.parse(catalogEl ? catalogEl.textContent || '{}' : '{}');
    } catch (error) {
        catalog = {};
    }

    if (!provinceSelect || !citySelect) {
        return;
    }

    function fillCities(selectedCity) {
        var provinceName = provinceSelect.value;
        var cities = catalog[provinceName] || [];
        var editing = form.classList.contains('is-editing');

        citySelect.innerHTML = '';
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Select municipality or city';
        citySelect.appendChild(empty);

        cities.forEach(function (city) {
            var option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            if (selectedCity && city === selectedCity) {
                option.selected = true;
            }
            citySelect.appendChild(option);
        });

        if (!provinceName) {
            citySelect.disabled = true;
            return;
        }

        citySelect.disabled = !editing;
    }

    provinceSelect.addEventListener('change', function () {
        var current = citySelect.value;
        var cities = catalog[provinceSelect.value] || [];
        fillCities(cities.indexOf(current) !== -1 ? current : '');
    });
});
</script>
@endpush
