@extends('layouts.admin')

@section('page-content')

@php
    $staffListQuery = array_filter([
        'location' => $locationKey ?? 'all',
        'club' => $selectedClubId ?: null,
        'search' => filled($search ?? '') ? $search : null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="admin-staff-page">
@include('partials.admin-staff-filter')

<section class="staff-stat-grid staff-stat-grid-4">
    <div class="staff-stat-card">
        <div class="staff-stat-icon blue">🧑‍💼</div>
        <div class="staff-stat-body">
            <h3>Active Staff</h3>
            <div class="value">{{ $staffStats['total'] }}</div>
            <div class="sub">Approved accounts</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon orange">⏱</div>
        <div class="staff-stat-body">
            <h3>Pending Approval</h3>
            <div class="value">{{ $staffStats['pending'] }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon green">✓</div>
        <div class="staff-stat-body">
            <h3>Approved Staff</h3>
            <div class="value">{{ $staffStats['approved'] }}</div>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon red">✕</div>
        <div class="staff-stat-body">
            <h3>Rejected</h3>
            <div class="value">{{ $staffStats['rejected'] }}</div>
        </div>
    </div>
</section>

@if($pendingStaff->isNotEmpty())
<div class="staff-card admin-staff-pending-card">
    <div class="staff-card-header">
        <h2>Pending Scholar Staff Registrations</h2>
        <span class="staff-badge orange">{{ $pendingStaff->count() }} awaiting approval</span>
    </div>
    <div class="staff-table-wrap admin-data-table-wrap admin-staff-pending-wrap">
        <table class="staff-table staff-manage-table admin-data-table admin-staff-pending-table">
            <thead>
                <tr>
                    <th class="admin-staff-pending-name-col">Staff Member</th>
                    <th>Account Details</th>
                    <th>Scholarship Club</th>
                    <th>Started Year</th>
                    <th>Date Registered</th>
                    <th class="staff-col-status">Status</th>
                    <th class="staff-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingStaff as $member)
                    <tr>
                        <td data-label="Staff Member" class="admin-staff-pending-name-col">
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($member->full_name, 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong class="admin-staff-pending-name">{{ $member->full_name }}</strong>
                                    <small>{{ $member->contactNumber() !== '' ? $member->contactNumber() : '—' }}</small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Account Details">{{ $member->email }}</td>
                        <td data-label="Scholarship Club">{{ $member->scholarshipClub?->name ?: '—' }}</td>
                        <td data-label="Started Year">{{ $member->staffStartedYearLabel() ?? '—' }}</td>
                        <td data-label="Date Registered">{{ $member->created_at->format('M j, Y g:i A') }}</td>
                        <td data-label="Status" class="staff-col-status"><span class="staff-badge orange">Pending</span></td>
                        <td data-label="Actions" class="staff-col-actions">
                            <div class="staff-action-group admin-staff-actions admin-staff-pending-actions">
                                <a href="{{ route('admin.staff.show', array_merge(['staffMember' => $member], $staffListQuery)) }}" class="staff-btn staff-btn-sm staff-btn-view" title="View this scholar staff account">View</a>
                                <form method="POST" action="{{ route('admin.staff.approve', $member) }}">
                                    @csrf
                                    @include('partials.admin-staff-return-fields')
                                    <button type="submit" class="staff-btn staff-btn-primary staff-btn-sm" title="Approve and activate this scholar staff account">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff.reject', $member) }}"
                                    data-no-loading="true"
                                    data-confirm="This permanently removes the pending Scholar Staff registration, associated club ownership when unused by other staff, and frees the email for a new registration. This cannot be undone."
                                    data-confirm-title="Reject and permanently remove this registration?"
                                    data-confirm-name="{{ $member->full_name }}"
                                    data-confirm-yes="Reject Permanently"
                                    data-confirm-no="Cancel"
                                    data-confirm-variant="danger">
                                    @csrf
                                    @include('partials.admin-staff-return-fields')
                                    <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger" title="Reject this registration">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="staff-card admin-staff-accounts-card">
    <div class="staff-card-header"><h2>All Scholar Staff Accounts</h2></div>
    <div class="staff-table-wrap admin-data-table-wrap admin-staff-accounts-wrap">
        <table class="staff-table staff-manage-table admin-data-table admin-staff-accounts-table">
            <thead>
                <tr>
                    <th class="admin-staff-accounts-name-col">Staff Member</th>
                    <th>Email</th>
                    <th>Contact Number</th>
                    <th>Scholarship Club</th>
                    <th>Started Year</th>
                    <th class="staff-col-status">Status</th>
                    <th class="staff-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($staffMembers as $member)
                    <tr>
                        <td data-label="Staff Member" class="admin-staff-accounts-name-col">
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($member->full_name, 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong class="admin-staff-accounts-name">{{ $member->full_name }}</strong>
                                </div>
                            </div>
                        </td>
                        <td data-label="Email">{{ $member->email }}</td>
                        <td data-label="Contact Number">{{ $member->contactNumber() !== '' ? $member->contactNumber() : '—' }}</td>
                        <td data-label="Scholarship Club">{{ $member->scholarshipClub?->name ?: '—' }}</td>
                        <td data-label="Started Year">{{ $member->staffStartedYearLabel() ?? '—' }}</td>
                        <td data-label="Status" class="staff-col-status">
                            <span class="staff-badge {{ $member->staffStatusBadgeClass() }}">{{ $member->staffStatusLabel() }}</span>
                        </td>
                        <td data-label="Actions" class="staff-col-actions">
                            <div class="staff-action-group admin-staff-actions admin-staff-accounts-actions">
                                <a href="{{ route('admin.staff.show', array_merge(['staffMember' => $member], $staffListQuery)) }}" class="staff-btn staff-btn-sm staff-btn-view" title="View this scholar staff account">View</a>
                                <button
                                    type="button"
                                    class="staff-btn staff-btn-sm staff-btn-more"
                                    data-staff-more
                                    data-staff-name="{{ $member->full_name }}"
                                    data-account-status="{{ $member->status }}"
                                    data-activate-url="{{ route('admin.staff.activate', $member) }}"
                                    data-delete-url="{{ route('admin.staff.delete', $member) }}"
                                    data-block-url="{{ route('admin.staff.block', $member) }}"
                                    data-suspend-url="{{ route('admin.staff.suspend', $member) }}"
                                    title="More staff account actions"
                                >More</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="7">No records found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $staffMembers->links() }}</div>
</div>
</div>

@include('partials.admin-staff-more-modal')

@endsection
