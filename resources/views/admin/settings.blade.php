@extends('layouts.admin')

@section('page-content')

<div class="admin-settings-tabs">
    <div class="staff-card">
        <div class="staff-card-header"><h2>Account Information</h2></div>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="staff-form-grid">
                <div class="staff-form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $admin->full_name) }}" required>
                </div>
                <div class="staff-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $admin->email) }}" required>
                </div>
                <div class="staff-form-group">
                    <label>Role</label>
                    <input type="text" value="Administrator" readonly>
                </div>
                <div class="staff-form-group">
                    <label>Scholar ID</label>
                    <input type="text" value="{{ $admin->scholar_id }}" readonly>
                </div>
            </div>
            <div class="admin-form-actions">
                <button type="submit" class="staff-btn staff-btn-primary">Save Account Info</button>
            </div>
        </form>
    </div>

    <div class="staff-card">
        <div class="staff-card-header"><h2>Change Password</h2></div>
        <form method="POST" action="{{ route('admin.settings.password') }}">
            @csrf
            @method('PUT')
            <div class="staff-form-grid">
                <div class="staff-form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="staff-form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                </div>
                <div class="staff-form-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>
            <div class="admin-form-actions">
                <button type="submit" class="staff-btn staff-btn-primary">Update Password</button>
            </div>
        </form>
    </div>

    <div class="staff-card">
        <div class="staff-card-header"><h2>Authorized Admin Accounts</h2></div>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($admins as $account)
                        <tr>
                            <td>{{ $account->full_name }}</td>
                            <td>{{ $account->email }}</td>
                            <td>
                                @if($account->id === $admin->id)
                                    <span class="staff-badge green">Current account</span>
                                @else
                                    <span class="staff-badge blue">Admin</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No admin accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
