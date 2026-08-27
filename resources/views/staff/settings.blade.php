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
            <button type="button" class="staff-btn" style="border-color:#1890ff;color:#1890ff">Change Password</button>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>.staff-muted{color:#6b7280;font-size:13px}</style>
@endpush
