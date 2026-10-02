@extends('layouts.admin')

@section('page-content')

<div class="admin-settings-tabs">
    <div class="staff-card">
        @php
            $adminProfileErrors = $errors->hasAny(['full_name', 'email']);
        @endphp
        <form method="POST" action="{{ route('admin.settings.update') }}" data-profile-edit-form data-start-editing="{{ $adminProfileErrors ? '1' : '0' }}">
            @csrf
            @method('PUT')
            <div class="staff-card-header">
                <h2>Account Information</h2>
                <button type="button" class="staff-btn staff-btn-primary" data-profile-edit>Edit</button>
            </div>
            <div class="staff-form-grid">
                <div class="staff-form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $admin->full_name) }}" required data-profile-editable data-saved-value="{{ $admin->full_name }}">
                </div>
                <div class="staff-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $admin->email) }}" required data-profile-editable data-saved-value="{{ $admin->email }}">
                </div>
                <div class="staff-form-group">
                    <label>Role</label>
                    <input type="text" value="Administrator" readonly disabled aria-readonly="true">
                </div>
                <div class="staff-form-group">
                    <label>Scholar ID</label>
                    <input type="text" value="{{ $admin->scholar_id }}" readonly disabled aria-readonly="true">
                </div>
            </div>
            <div class="admin-form-actions profile-edit-actions">
                <button type="button" class="staff-btn" data-profile-cancel hidden>Cancel</button>
                <button type="submit" class="staff-btn staff-btn-primary" data-profile-save hidden disabled>Save Changes</button>
            </div>
        </form>
    </div>

    <div class="staff-card admin-academic-year-card">
        <div class="staff-card-header">
            <h2>Academic Year</h2>
            <span class="staff-badge green">Active: {{ $activeAcademicYear->periodLabel() }}</span>
        </div>

        <form
            method="POST"
            action="{{ $editingAcademicYear ? route('admin.settings.academic-years.update', $editingAcademicYear) : route('admin.settings.academic-years.store') }}"
            class="admin-academic-year-form"
        >
            @csrf
            @if($editingAcademicYear)
                @method('PUT')
            @endif
            <div class="staff-form-grid">
                <div class="staff-form-group">
                    <label for="academic_year">Academic Year</label>
                    <input
                        type="text"
                        id="academic_year"
                        name="academic_year"
                        value="{{ old('academic_year', $editingAcademicYear?->periodLabel()) }}"
                        placeholder="2026–2027"
                        required
                        autocomplete="off"
                    >
                    <small class="staff-muted">Use the format 2026–2027. The second year must follow the first.</small>
                    @error('academic_year')
                        <div class="staff-field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="admin-form-actions">
                @if($editingAcademicYear)
                    <a href="{{ route('admin.settings') }}" class="staff-btn">Cancel</a>
                    <button type="submit" class="staff-btn staff-btn-primary">Save Academic Year</button>
                @else
                    <button type="submit" class="staff-btn staff-btn-primary">Add Academic Year</button>
                @endif
            </div>
        </form>

        <div class="staff-table-wrap" style="margin-top:16px">
            <table class="staff-table staff-stack-table">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($academicYears as $year)
                        <tr>
                            <td data-label="Academic Year"><strong>{{ $year->periodLabel() }}</strong></td>
                            <td data-label="Status">
                                @if($year->is_active)
                                    <span class="staff-badge green">Active</span>
                                @else
                                    <span class="staff-badge gray">Inactive</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div class="staff-action-group">
                                    <a href="{{ route('admin.settings', ['edit' => $year->id]) }}" class="staff-btn staff-btn-sm">Edit</a>
                                    @if($year->is_active)
                                        @if($academicYears->count() > 1)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.settings.academic-years.deactivate', $year) }}"
                                                data-confirm="This will deactivate {{ $year->periodLabel() }}. The most recent remaining academic year will become active and will be used across scholars, events, attendance, service hours, documents, and reports."
                                                data-confirm-title="Deactivate this academic year?"
                                                data-confirm-yes="Deactivate"
                                                data-confirm-no="Cancel"
                                                data-confirm-variant="danger"
                                                data-no-loading
                                            >
                                                @csrf
                                                <button type="submit" class="staff-btn staff-btn-sm">Deactivate</button>
                                            </form>
                                        @endif
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.settings.academic-years.activate', $year) }}"
                                            data-confirm="This will make {{ $year->periodLabel() }} the active academic year for scholars, events, attendance, service hours, documents, and reports. {{ $activeAcademicYear->periodLabel() }} will be deactivated."
                                            data-confirm-title="Activate this academic year?"
                                            data-confirm-yes="Activate"
                                            data-confirm-no="Cancel"
                                            data-no-loading
                                        >
                                            @csrf
                                            <button type="submit" class="staff-btn staff-btn-sm staff-btn-primary">Activate</button>
                                        </form>
                                    @endif
                                    @php
                                        $yearUsage = $academicYearUsage[$year->id] ?? ['has_records' => false, 'warning' => ''];
                                        $yearLabel = $year->periodLabel();
                                    @endphp
                                    @if($year->is_active)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.settings.academic-years.destroy', $year) }}"
                                            data-confirm="{{ $yearLabel }} is the active academic year and cannot be deleted until another academic year is set as active."
                                            data-confirm-title="Delete {{ $yearLabel }}?"
                                            data-confirm-name="{{ $yearLabel }}"
                                            data-confirm-yes="Delete"
                                            data-confirm-no="Cancel"
                                            data-confirm-variant="danger"
                                            data-no-loading
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Delete</button>
                                        </form>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.settings.academic-years.destroy', $year) }}"
                                            data-confirm="This will permanently delete {{ $yearLabel }} from Academic Year management."
                                            data-confirm-title="Delete {{ $yearLabel }}?"
                                            data-confirm-name="{{ $yearLabel }}"
                                            data-confirm-note="{{ ($yearUsage['has_records'] ?? false) ? ($yearUsage['warning'].' This cannot be undone.') : 'This cannot be undone.' }}"
                                            data-confirm-yes="Delete"
                                            data-confirm-no="Cancel"
                                            data-confirm-variant="danger"
                                            data-no-loading
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="staff-table-empty"><td colspan="3">No academic years found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
            <table class="staff-table staff-stack-table">
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
