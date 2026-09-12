@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')
@include('partials.admin-scope-banner')

<section class="staff-stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">🧑‍💼</div><div class="staff-stat-body"><h3>Active Staff</h3><div class="value">{{ $staffStats['total'] }}</div><div class="sub">Approved accounts</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Approval</h3><div class="value">{{ $staffStats['pending'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved Staff</h3><div class="value">{{ $staffStats['approved'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✕</div><div class="staff-stat-body"><h3>Rejected</h3><div class="value">{{ $staffStats['rejected'] }}</div></div></div>
</section>

<div class="staff-card" style="margin-bottom:20px;padding:14px 18px;background:#fff2f1;border-color:#fecaca">
    <strong>Scholar Staff Registration Approval System</strong>
    <p class="staff-muted" style="margin:6px 0 0">
        New scholar staff registrations appear below with a <strong>Pending</strong> status. Pending accounts cannot log in or access Scholar Staff features.
        Use <strong>Approve</strong> to activate an account, or <strong>Reject</strong> to deny access and mark the registration as rejected.
        Approvals are scoped to the selected City or Province Scholarship Program filter.
    </p>
</div>

@if($pendingStaff->isNotEmpty())
<div class="staff-card" style="margin-bottom:20px;border-color:#fed7aa">
    <div class="staff-card-header">
        <h2>Pending Scholar Staff Registrations</h2>
        <span class="staff-badge orange">{{ $pendingStaff->count() }} awaiting approval</span>
    </div>
    <div class="staff-table-wrap">
        <table class="staff-table">
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
                        <td>
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($member->full_name, 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $member->full_name }}</strong>
                                    <small>{{ $member->scholar_id }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->scholarshipProgram?->programLabel() ?? 'Unassigned' }}</td>
                        <td>{{ $member->created_at->format('M j, Y g:i A') }}</td>
                        <td><span class="staff-badge orange">Pending</span></td>
                        <td>
                            <form method="POST" action="{{ route('admin.staff.approve', $member) }}" style="display:inline">
                                @csrf
                                @include('partials.admin-scope-fields')
                                <button type="submit" class="staff-btn staff-btn-primary staff-btn-sm" title="Approve and activate this scholar staff account">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.staff.reject', $member) }}" style="display:inline" onsubmit="return confirm('Reject this scholar staff registration? The account will be marked as Rejected and will not be able to log in.');">
                                @csrf
                                @include('partials.admin-scope-fields')
                                <button type="submit" class="staff-btn staff-btn-sm" style="border-color:#ef4444;color:#ef4444" title="Reject this registration">Reject</button>
                            </form>
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
        <table class="staff-table">
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
                        <td>
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($member->full_name, 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $member->full_name }}</strong>
                                    <small>{{ $member->scholar_id }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->scholarshipProgram?->programLabel() ?? 'Unassigned' }}</td>
                        <td>
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
                        <td>
                            <span class="staff-muted">—</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No approved or rejected scholar staff found for this scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $staffMembers->links() }}</div>
</div>

@endsection
