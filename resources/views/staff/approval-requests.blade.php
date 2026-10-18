@extends('layouts.staff')

@section('page-content')

<section class="staff-stat-grid staff-stat-grid-3">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">📋</div><div class="staff-stat-body"><h3>Total Scholars</h3><div class="value" data-approval-stat="total">{{ $stats['total'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Requests</h3><div class="value" data-approval-stat="pending">{{ $stats['pending'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved Scholars</h3><div class="value" data-approval-stat="approved">{{ $stats['approved'] }}</div></div></div>
</section>

<div class="staff-info-banner">
    <strong>Account Approval System</strong>
    <p>Pending scholar accounts cannot access full system features until approved. Rejected accounts are permanently removed from the database.</p>
</div>

<form method="GET" class="staff-filter-bar">
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by name, email or ID...">
    </div>
    <button type="submit" class="staff-btn">Search</button>
</form>

<div class="staff-card staff-approval-card">
    <div class="staff-table-wrap staff-data-table-wrap">
        <table class="staff-table staff-manage-table staff-approval-table staff-data-table">
            <thead>
                <tr>
                    <th>Scholar Information</th>
                    <th>Account Details</th>
                    <th>No./Street</th>
                    <th>Municipality / City</th>
                    <th>Province</th>
                    <th>Date Registered</th>
                    <th class="staff-col-status">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $request)
                    <tr data-scholar-id="{{ $request->id }}">
                        <td data-label="Scholar Information">
                            <div class="staff-stack-value">
                                <div class="staff-scholar-cell">
                                    <x-user-avatar :user="$request" class="staff-scholar-avatar" />
                                    <div class="staff-scholar-meta">
                                        <a href="{{ route('staff.scholars.show', $request) }}" class="staff-scholar-name-link" title="View scholar registration">{{ $request->full_name }}</a>
                                        <small>{{ $request->scholar_id }}</small>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Account Details">
                            <div class="staff-stack-value staff-account-details">
                                <span>{{ $request->email }}</span>
                                <span class="staff-muted">{{ $request->cellphone_number ?? '—' }}</span>
                            </div>
                        </td>
                        <td data-label="No./Street">
                            <div class="staff-stack-value">{{ $request->streetName() ?: '—' }}</div>
                        </td>
                        <td data-label="Municipality / City">
                            <div class="staff-stack-value">{{ $request->municipalityName() ?: ($request->scholarshipClubCity() ?? '—') }}</div>
                        </td>
                        <td data-label="Province">
                            <div class="staff-stack-value">{{ $request->provinceName() ?: ($request->scholarshipClubProvince() ?? '—') }}</div>
                        </td>
                        <td data-label="Date Registered">
                            <div class="staff-stack-value">{{ $request->created_at->format('M j, Y g:i A') }}</div>
                        </td>
                        <td data-label="Status" class="staff-col-status">
                            <div class="staff-stack-value staff-approval-status">
                                <span class="staff-badge orange" data-approval-status>Pending</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="7">No approval requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $requests->links() }}</div>
</div>

@endsection
