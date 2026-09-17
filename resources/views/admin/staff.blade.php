@extends('layouts.admin')

@section('page-content')

<div class="admin-staff-page">
@include('partials.admin-location-filter')
@include('partials.admin-scope-banner')

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

<div class="staff-info-banner admin-staff-notice">
    <strong>Scholar Staff Registration Approval System</strong>
    <p>
        New scholar staff registrations appear below with a <strong>Pending</strong> status. Pending accounts cannot log in or access Scholar Staff features.
        Use <strong>Approve</strong> to activate an account, or <strong>Reject</strong> to deny access and mark the registration as rejected.
        Approvals are scoped to the selected City or Province Scholarship Program filter.
    </p>
</div>

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
                    <th>Assigned Program</th>
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
                                    <small>{{ $member->scholar_id }}</small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Account Details">{{ $member->email }}</td>
                        <td data-label="Assigned Program">{{ $member->scholarshipProgram?->programLabel() ?? 'Unassigned' }}</td>
                        <td data-label="Date Registered">{{ $member->created_at->format('M j, Y g:i A') }}</td>
                        <td data-label="Status"><span class="staff-badge orange">Pending</span></td>
                        <td data-label="Actions">
                            <div class="staff-action-group">
                                <form method="POST" action="{{ route('admin.staff.approve', $member) }}">
                                    @csrf
                                    @include('partials.admin-scope-fields')
                                    <button type="submit" class="staff-btn staff-btn-primary staff-btn-sm" title="Approve and activate this scholar staff account">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff.reject', $member) }}" onsubmit="return confirm('Reject this scholar staff registration? The account will be marked as Rejected and will not be able to log in.');">
                                    @csrf
                                    @include('partials.admin-scope-fields')
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

<form method="GET" class="staff-filter-bar">
    @include('partials.admin-scope-fields')
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by name, email, or staff number...">
    </div>
    <button type="submit" class="staff-btn staff-btn-primary">Search</button>
</form>

<div class="staff-card">
    <div class="staff-card-header"><h2>All Scholar Staff Accounts</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Email</th>
                    <th>Assigned Program</th>
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
                                    <small>{{ $member->scholar_id }}</small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Email">{{ $member->email }}</td>
                        <td data-label="Assigned Program">{{ $member->scholarshipProgram?->programLabel() ?? 'Unassigned' }}</td>
                        <td data-label="Status">
                            @php
                                $statusClass = match($member->status) {
                                    'approved' => 'green',
                                    'pending' => 'orange',
                                    'rejected' => 'red',
                                    default => 'gray',
                                };
                            @endphp
                            <span class="staff-badge {{ $statusClass }}">{{ ucfirst($member->status ?? 'approved') }}</span>
                        </td>
                        <td data-label="Actions">
                            <span class="staff-muted">—</span>
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="5">No approved or rejected scholar staff found for this scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $staffMembers->links() }}</div>
</div>
</div>

@endsection
