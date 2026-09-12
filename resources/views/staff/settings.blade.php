@extends('layouts.staff')

@section('page-content')

<div class="staff-settings-grid">
    <div class="staff-card">
        <div class="staff-card-header">
            <h2>⚙ General Settings</h2>
        </div>
        <form class="staff-form-grid">
            <div class="staff-form-group">
                <label for="app_name">Application Name</label>
                <input type="text" id="app_name" value="Batang Surigaonon Scholar's App" readonly>
            </div>
            <div class="staff-form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" value="{{ $staff->email }}" readonly>
            </div>
            <div class="staff-form-group">
                <label for="contact">Contact Number</label>
                <input type="text" id="contact" value="{{ $staff->phone ?? '' }}" readonly>
            </div>
            <div class="staff-form-group">
                <label for="language">System Language</label>
                <select id="language" disabled>
                    <option selected>English</option>
                </select>
            </div>
        </form>
        <div style="text-align:right;margin-top:16px">
            <button type="button" class="staff-btn staff-btn-primary">Save Changes</button>
        </div>
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
