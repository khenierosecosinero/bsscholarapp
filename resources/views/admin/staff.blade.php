@extends('layouts.admin')

@section('page-content')

@php
    $staffListQuery = array_filter([
        'region' => $selectedRegion ?: null,
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
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Account Details</th>
                    <th>Scholarship Club</th>
                    <th>Date Registered</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingStaff as $member)
                    <tr>
                        <td data-label="Staff Member">
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($member->full_name, 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $member->full_name }}</strong>
                                    <small>{{ $member->contactNumber() !== '' ? $member->contactNumber() : '—' }}</small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Account Details">{{ $member->email }}</td>
                        <td data-label="Scholarship Club">{{ $member->scholarshipClub?->name ?: '—' }}</td>
                        <td data-label="Date Registered">{{ $member->created_at->format('M j, Y g:i A') }}</td>
                        <td data-label="Status"><span class="staff-badge orange">Pending</span></td>
                        <td data-label="Actions">
                            <div class="staff-action-group">
                                <a href="{{ route('admin.staff.show', array_merge(['staffMember' => $member], $staffListQuery)) }}" class="staff-btn staff-btn-sm staff-btn-view" title="View this scholar staff account">View</a>
                                <form method="POST" action="{{ route('admin.staff.approve', $member) }}">
                                    @csrf
                                    @include('partials.admin-staff-return-fields')
                                    <button type="submit" class="staff-btn staff-btn-primary staff-btn-sm" title="Approve and activate this scholar staff account">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff.reject', $member) }}"
                                    data-no-loading="true"
                                    data-confirm="The account will be marked as Rejected and will not be able to log in."
                                    data-confirm-title="Reject this scholar staff registration?"
                                    data-confirm-name="{{ $member->full_name }}"
                                    data-confirm-yes="Reject"
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

<div class="staff-card">
    <div class="staff-card-header"><h2>All Scholar Staff Accounts</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Email</th>
                    <th>Contact Number</th>
                    <th>Scholarship Club</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($staffMembers as $member)
                    <tr>
                        <td data-label="Staff Member">
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($member->full_name, 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $member->full_name }}</strong>
                                </div>
                            </div>
                        </td>
                        <td data-label="Email">{{ $member->email }}</td>
                        <td data-label="Contact Number">{{ $member->contactNumber() !== '' ? $member->contactNumber() : '—' }}</td>
                        <td data-label="Scholarship Club">{{ $member->scholarshipClub?->name ?: '—' }}</td>
                        <td data-label="Status">
                            <span class="staff-badge {{ $member->staffStatusBadgeClass() }}">{{ $member->staffStatusLabel() }}</span>
                        </td>
                        <td data-label="Actions">
                            <div class="staff-action-group">
                                <a href="{{ route('admin.staff.show', array_merge(['staffMember' => $member], $staffListQuery)) }}" class="staff-btn staff-btn-sm staff-btn-view" title="View this scholar staff account">View</a>
                                <button
                                    type="button"
                                    class="staff-btn staff-btn-sm staff-btn-more"
                                    data-staff-more
                                    data-staff-name="{{ $member->full_name }}"
                                    data-activate-url="{{ route('admin.staff.activate', $member) }}"
                                    data-deactivate-url="{{ route('admin.staff.deactivate', $member) }}"
                                    data-delete-url="{{ route('admin.staff.delete', $member) }}"
                                    title="More staff account actions"
                                >More</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="6">No records found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $staffMembers->links() }}</div>
</div>
</div>

@include('partials.admin-staff-more-modal')

@endsection
