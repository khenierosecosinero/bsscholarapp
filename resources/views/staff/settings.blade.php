@extends('layouts.staff')

@section('page-content')

<div class="staff-settings-grid">
    <div class="staff-card">
        @php
            $staffProfileErrors = $errors->hasAny(['scholarship_club_name', 'scholarship_program_id', 'cellphone_number']);
        @endphp
        <form method="POST" action="{{ route('staff.settings.club') }}" class="staff-form-grid" data-profile-edit-form data-start-editing="{{ $staffProfileErrors ? '1' : '0' }}">
            @csrf
            @method('PUT')
            <div class="staff-card-header" style="grid-column:1/-1;margin-bottom:0">
                <h2>⚙ General Settings</h2>
                <button type="button" class="staff-btn staff-btn-primary" data-profile-edit>Edit</button>
            </div>
            <div class="staff-form-group">
                <label for="app_name">Scholarship Club Name</label>
                <input type="text" id="app_name" name="scholarship_club_name" value="{{ old('scholarship_club_name', $staff->scholarshipClubName()) }}" required maxlength="255" data-profile-editable data-saved-value="{{ $staff->scholarshipClubName() }}">
                <p class="staff-muted" style="margin:6px 0 0">Scholarship Club linked to this staff account. Scholars you manage belong to this club only.</p>
                @error('scholarship_club_name')
                    <p class="staff-field-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="staff-form-group staff-form-group-wide">
                @include('partials.staff-location-select', [
                    'locationTree' => $locationTree ?? [],
                    'requireCity' => true,
                    'addressMode' => true,
                    'selectedId' => old('scholarship_program_id', $staff->scholarshipClub?->scholarship_program_id ?? $staff->scholarship_program_id),
                ])
                <p class="staff-muted" style="margin:6px 0 0">Province and Municipality/City are the official address of this Scholarship Club.</p>
                @error('scholarship_program_id')
                    <p class="staff-field-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="staff-form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" value="{{ $staff->email }}" readonly disabled aria-readonly="true">
            </div>
            <div class="staff-form-group">
                <label for="contact">Contact Number</label>
                <input type="text" id="contact" name="cellphone_number" value="{{ old('cellphone_number', $staff->contactNumber()) }}" maxlength="50" autocomplete="tel" data-profile-editable data-saved-value="{{ $staff->contactNumber() }}">
                @error('cellphone_number')
                    <p class="staff-field-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="staff-form-group">
                <label for="language">System Language</label>
                <select id="language" disabled>
                    <option selected>English</option>
                </select>
            </div>
            <div class="staff-form-group profile-edit-actions" style="grid-column:1/-1;margin:0">
                <button type="button" class="staff-btn" data-profile-cancel hidden>Cancel</button>
                <button type="submit" class="staff-btn staff-btn-primary" data-profile-save hidden disabled>Save Changes</button>
            </div>
        </form>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>🏫 School/University Management</h2>
            @if($staff->scholarship_club_id)
                <a href="{{ route('staff.settings.schools.create') }}" class="staff-card-link">+ Add School/University</a>
            @endif
        </div>
        <p class="staff-muted" style="margin:0 0 16px">Scholars who register or update their profile under {{ $staff->scholarshipClubName() }} can only choose from these School/University names.</p>
        @if(! $staff->scholarship_club_id)
            <p class="staff-muted" style="margin:0">Save your Scholarship Club in General Settings before adding School/University names.</p>
        @elseif(($schools ?? collect())->isEmpty())
            <p class="staff-muted" style="margin:0 0 12px">No School/University has been added yet.</p>
            <a href="{{ route('staff.settings.schools.create') }}" class="staff-btn staff-btn-primary">+ Add School/University</a>
        @else
            <div class="staff-school-list">
                @foreach($schools as $school)
                    <div class="staff-school-row">
                        <div>
                            <strong>{{ $school->name }}</strong>
                        </div>
                        <div class="staff-school-actions">
                            <a href="{{ route('staff.settings.schools.edit', $school) }}" class="staff-btn staff-btn-sm">Edit</a>
                            <form method="POST" action="{{ route('staff.settings.schools.destroy', $school) }}" data-confirm="Remove {{ $school->name }} from this Scholarship Club?" data-confirm-title="Remove School/University?" data-confirm-yes="Remove" data-confirm-no="Cancel">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>🔔 Notification Settings</h2>
        </div>
        <div class="staff-toggle-row">
            <div>
                <strong>Email Notifications</strong>
                <div class="staff-muted">Receive email alerts for new registrations and submissions.</div>
            </div>
            <div class="staff-toggle" aria-hidden="true"></div>
        </div>
        <div class="staff-toggle-row">
            <div>
                <strong>System Alerts</strong>
                <div class="staff-muted">Show in-app notifications for pending approvals.</div>
            </div>
            <div class="staff-toggle" aria-hidden="true"></div>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>🛡 Security Settings</h2>
        </div>
        <div class="staff-toggle-row" style="border-bottom:none">
            <div>
                <strong>Password</strong>
                <div class="staff-muted">Update your account password regularly.</div>
            </div>
            <button type="button" class="staff-btn staff-change-password-btn" id="open-change-password">Change Password</button>
        </div>
    </div>
</div>

@php
    $passwordErrors = $errors->hasAny(['current_password', 'password', 'password_confirmation']);
@endphp

<div class="staff-confirm-modal{{ $passwordErrors ? ' is-open' : '' }}" id="staff-password-modal" aria-hidden="{{ $passwordErrors ? 'false' : 'true' }}">
    <div class="staff-confirm-backdrop" data-password-close></div>
    <div class="staff-password-dialog" role="dialog" aria-modal="true" aria-labelledby="staff-password-title">
        <h3 id="staff-password-title" class="staff-confirm-title">Change Password</h3>
        <p class="staff-muted staff-password-hint">Enter your current password, then choose a new password with at least 8 characters, including a letter and a number.</p>

        <form method="POST" action="{{ route('staff.settings.password') }}" id="staff-password-form">
            @csrf
            @method('PUT')

            <div class="staff-form-group">
                <label for="current_password">Current Password</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="{{ $errors->has('current_password') ? 'is-invalid' : '' }}"
                >
                @error('current_password')
                    <p class="staff-field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="staff-form-group">
                <label for="password">New Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                >
                @error('password')
                    <p class="staff-field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="staff-form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    class="{{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}"
                >
                @error('password_confirmation')
                    <p class="staff-field-error">{{ $message }}</p>
                @enderror
                <p class="staff-field-error" id="password-match-error" hidden>New password and confirm new password must match.</p>
            </div>

            <div class="staff-confirm-actions">
                <button type="button" class="staff-btn staff-confirm-no" data-password-close>Cancel</button>
                <button type="submit" class="staff-btn staff-btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('styles')
<style>
.staff-muted{color:#6b7280;font-size:13px}
.staff-form-group-wide{grid-column:1/-1}
.staff-school-list{display:flex;flex-direction:column;gap:10px}
.staff-school-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid var(--staff-border,#e5e7eb)}
.staff-school-row:last-child{border-bottom:none;padding-bottom:0}
.staff-school-actions{display:flex;align-items:center;gap:8px}
.staff-settings-grid .location-cascade{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.staff-settings-grid .location-cascade-label{display:block;font-size:13px;font-weight:600;color:#374151;margin:0 0 6px}
.staff-settings-grid .location-cascade select.form-input{width:100%;padding:10px 12px;border:1px solid var(--staff-border,#e5e7eb);border-radius:8px;background:#fff;font:inherit;box-sizing:border-box}
.staff-settings-grid .location-cascade select:disabled{color:#9ca3af;cursor:not-allowed;background:#f3f4f6}
@media (max-width:720px){.staff-settings-grid .location-cascade{grid-template-columns:1fr}}
.staff-change-password-btn{border-color:#1890ff;color:#1890ff}
.staff-password-dialog{
    position:relative;
    width:min(100%,440px);
    background:#fff;
    border-radius:16px;
    border:1px solid var(--staff-border);
    box-shadow:0 24px 60px rgba(15,39,68,.2);
    padding:32px 28px 24px;
    text-align:left;
    animation:staffConfirmIn .2s ease;
}
.staff-password-dialog .staff-confirm-title{text-align:left}
.staff-password-hint{margin:0 0 18px}
.staff-password-dialog .staff-form-group{margin-bottom:14px}
.staff-password-dialog input.is-invalid{border-color:#dc2626}
.staff-field-error{margin:6px 0 0;color:#dc2626;font-size:12px;font-weight:600}
.staff-password-dialog .staff-confirm-actions{margin-top:8px;justify-content:flex-end}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('staff-password-modal');
    var openBtn = document.getElementById('open-change-password');
    var form = document.getElementById('staff-password-form');
    if (!modal || !openBtn || !form) return;

    var currentInput = document.getElementById('current_password');
    var newInput = document.getElementById('password');
    var confirmInput = document.getElementById('password_confirmation');
    var matchError = document.getElementById('password-match-error');

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        if (currentInput) currentInput.focus();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        form.reset();
        if (matchError) matchError.hidden = true;
        form.querySelectorAll('.is-invalid').forEach(function (input) {
            input.classList.remove('is-invalid');
        });
        form.querySelectorAll('.staff-field-error').forEach(function (error) {
            if (error.id !== 'password-match-error') error.remove();
        });
    }

    openBtn.addEventListener('click', openModal);

    modal.querySelectorAll('[data-password-close]').forEach(function (trigger) {
        trigger.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    form.addEventListener('submit', function (event) {
        var currentPassword = (currentInput.value || '').trim();
        var newPassword = newInput.value || '';
        var confirmPassword = confirmInput.value || '';

        [currentInput, newInput, confirmInput].forEach(function (input) {
            input.classList.remove('is-invalid');
        });
        if (matchError) matchError.hidden = true;

        if (!currentPassword || !newPassword || !confirmPassword) {
            event.preventDefault();
            if (!currentPassword) currentInput.classList.add('is-invalid');
            if (!newPassword) newInput.classList.add('is-invalid');
            if (!confirmPassword) confirmInput.classList.add('is-invalid');
            form.reportValidity();
            return;
        }

        if (newPassword !== confirmPassword) {
            event.preventDefault();
            newInput.classList.add('is-invalid');
            confirmInput.classList.add('is-invalid');
            if (matchError) matchError.hidden = false;
            confirmInput.focus();
        }
    });
});
</script>
@endpush
