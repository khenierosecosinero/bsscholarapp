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

    @include('partials.admin-academic-year-selector', [
        'title' => 'Viewing Academic Year',
        'inputId' => 'admin-settings-view-year',
    ])

    <div class="staff-card admin-academic-year-card">
        <div class="staff-card-header">
            <h2>Manage Academic Years</h2>
        </div>
        <p class="staff-muted" style="margin:0 0 14px">
            Add, edit, or delete academic years available across the Admin Portal. The viewing year above controls which year’s records appear on Dashboard, Scholars, Events, Service Hours, Documents, and Reports.
        </p>

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
            <table class="staff-table staff-stack-table staff-manage-table">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th class="staff-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($academicYears as $year)
                        <tr>
                            <td data-label="Academic Year"><strong>{{ $year->periodLabel() }}</strong></td>
                            <td data-label="Actions" class="staff-col-actions">
                                <div class="staff-action-group">
                                    <a href="{{ route('admin.settings', ['edit' => $year->id]) }}" class="staff-btn staff-btn-sm">Edit</a>
                                    @php
                                        $yearUsage = $academicYearUsage[$year->id] ?? ['has_records' => false, 'warning' => ''];
                                        $yearLabel = $year->periodLabel();
                                    @endphp
                                    <form
                                        method="POST"
                                        action="{{ route('admin.settings.academic-years.destroy', $year) }}"
                                        data-confirm="This will remove {{ $yearLabel }} from Academic Year management. Attendance and service-hour records already saved for this year stay in the system."
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
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="staff-table-empty"><td colspan="2">No academic years found.</td></tr>
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

    <div class="staff-card" data-backup-emails>
        <div class="staff-card-header">
            <h2>Backup Recovery Emails</h2>
        </div>
        <p class="staff-muted" style="margin:0 0 14px">
            Add one or more backup emails for Forgot Password. Only verified addresses can receive recovery codes.
            Editing an address clears verification until the new inbox confirms the change.
        </p>

        @error('backup_email')
            <div class="staff-field-error" style="margin-bottom:10px">{{ $message }}</div>
        @enderror

        <div data-backup-list>
            @forelse(($backupEmails ?? []) as $backup)
                <div class="backup-email-row" style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:12px">
                    <form method="POST" action="{{ route('admin.settings.backup-emails.update', $backup) }}" class="staff-form-grid">
                        @csrf
                        @method('PUT')
                        <div class="staff-form-group">
                            <label for="backup_email_{{ $backup->id }}">Backup email</label>
                            <input
                                type="email"
                                id="backup_email_{{ $backup->id }}"
                                name="backup_email"
                                value="{{ old('backup_email', $backup->email) }}"
                                required
                                autocomplete="off"
                            >
                            <small class="staff-muted">
                                @if($backup->isVerified())
                                    Status: <strong>Verified</strong> — usable for password recovery
                                @else
                                    Status: <strong>Pending verification</strong> — not usable for recovery yet
                                @endif
                            </small>
                        </div>
                        <div class="admin-form-actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                            <button type="submit" class="staff-btn staff-btn-primary">Save</button>
                            @unless($backup->isVerified())
                                <button
                                    type="submit"
                                    class="staff-btn"
                                    form="backup-resend-{{ $backup->id }}"
                                >Resend verification</button>
                            @endunless
                            <button
                                type="submit"
                                class="staff-btn"
                                form="backup-remove-{{ $backup->id }}"
                            >Remove</button>
                        </div>
                    </form>
                    @unless($backup->isVerified())
                        <form id="backup-resend-{{ $backup->id }}" method="POST" action="{{ route('admin.settings.backup-emails.resend', $backup) }}">
                            @csrf
                        </form>
                    @endunless
                    <form
                        id="backup-remove-{{ $backup->id }}"
                        method="POST"
                        action="{{ route('admin.settings.backup-emails.destroy', $backup) }}"
                        data-confirm="This backup email will no longer be usable for password recovery."
                        data-confirm-title="Remove this backup recovery email?"
                        data-confirm-name="{{ $backup->email }}"
                        data-confirm-yes="Remove"
                        data-confirm-no="Cancel"
                        data-confirm-variant="danger"
                    >
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            @empty
                <p class="staff-muted" data-backup-empty style="margin:0 0 12px">No backup recovery emails yet. Use + Backup Email to add one.</p>
            @endforelse
        </div>

        <div data-backup-drafts></div>

        <div class="admin-form-actions" style="margin-top:8px">
            <button type="button" class="staff-btn staff-btn-primary" data-backup-add>+ Backup Email</button>
        </div>

        <template id="backup-email-draft-template">
            <div class="backup-email-row backup-email-draft" style="border:1px dashed #9ca3af;border-radius:10px;padding:14px;margin-top:12px">
                <form method="POST" action="{{ route('admin.settings.backup-emails.store') }}" class="staff-form-grid">
                    @csrf
                    <div class="staff-form-group">
                        <label>New backup email</label>
                        <input type="email" name="backup_email" required autocomplete="off" placeholder="name@example.com">
                        <small class="staff-muted">Status: <strong>Not saved</strong> — save to send a verification link</small>
                    </div>
                    <div class="admin-form-actions" style="display:flex;flex-wrap:wrap;gap:8px">
                        <button type="submit" class="staff-btn staff-btn-primary">Save &amp; verify</button>
                        <button type="button" class="staff-btn" data-backup-draft-remove>Remove</button>
                    </div>
                </form>
            </div>
        </template>
    </div>

    <script>
        (function () {
            var root = document.querySelector('[data-backup-emails]');
            if (!root) return;
            var drafts = root.querySelector('[data-backup-drafts]');
            var template = document.getElementById('backup-email-draft-template');
            var addBtn = root.querySelector('[data-backup-add]');
            var emptyNote = root.querySelector('[data-backup-empty]');
            var storeUrl = @json(route('admin.settings.backup-emails.store'));
            var defaultEmail = @json(($backupEmails ?? collect())->isEmpty() ? ($defaultBackupEmail ?? '') : '');

            function addDraft(prefill) {
                if (!drafts || !template) return;
                var node = template.content.cloneNode(true);
                var row = node.querySelector('.backup-email-draft');
                var input = node.querySelector('input[type="email"]');
                var form = node.querySelector('form');
                if (form) form.action = storeUrl;
                if (input && prefill) input.value = prefill;
                var removeBtn = node.querySelector('[data-backup-draft-remove]');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        if (row && row.parentNode) row.parentNode.removeChild(row);
                    });
                }
                drafts.appendChild(node);
                if (emptyNote) emptyNote.hidden = true;
                if (input) input.focus();
            }

            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    addDraft('');
                });
            }

            @if($errors->has('backup_email'))
                addDraft(@json(old('backup_email', '')));
            @elseif(($backupEmails ?? collect())->isEmpty() && $defaultBackupEmail)
                addDraft(defaultEmail);
            @endif
        })();
    </script>

    <div class="staff-card">
        <div class="staff-card-header"><h2>Authorized Admin Accounts</h2></div>
        <div class="staff-table-wrap">
            <table class="staff-table staff-stack-table staff-manage-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th class="staff-col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($admins as $account)
                        <tr>
                            <td>{{ $account->full_name }}</td>
                            <td>{{ $account->email }}</td>
                            <td class="staff-col-status">
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
