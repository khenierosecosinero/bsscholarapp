@extends('layouts.staff')

@section('page-content')

<section class="staff-stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">📋</div><div class="staff-stat-body"><h3>Total Scholars</h3><div class="value">{{ $stats['total'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Requests</h3><div class="value">{{ $stats['pending'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved Scholars</h3><div class="value">{{ $stats['approved'] }}</div></div></div>
</section>

<div class="staff-card" style="margin-bottom:20px;padding:14px 18px;background:#eff6ff;border-color:#bfdbfe">
    <strong>Account Approval System</strong>
    <p class="staff-muted" style="margin:6px 0 0">Pending scholar accounts cannot access full system features until approved. Rejected accounts are permanently removed from the database.</p>
</div>

<form method="GET" class="staff-filter-bar">
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by name, email or ID...">
    </div>
    <button type="submit" class="staff-btn">Search</button>
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar Information</th>
                    <th>Account Details</th>
                    <th>Municipality / City</th>
                    <th>Province</th>
                    <th>Date Registered</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $request)
                    <tr>
                        <td>
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($request->full_name, 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $request->full_name }}</strong>
                                    <small>{{ $request->scholar_id }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            {{ $request->email }}
                            <div class="staff-muted">{{ $request->cellphone_number ?? '—' }}</div>
                        </td>
                        <td>{{ $request->municipalityName() ?? '—' }}</td>
                        <td>{{ $request->provinceName() ?? '—' }}</td>
                        <td>{{ $request->created_at->format('M j, Y g:i A') }}</td>
                        <td>
                            <span class="staff-badge orange">Pending</span>
                        </td>
                        <td>
                            <a href="{{ route('staff.scholars.show', $request) }}" class="staff-action-btn" title="View">👁</a>
                            <form method="POST" action="{{ route('staff.scholars.approve', $request) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="staff-action-btn" title="Approve">✓</button>
                            </form>
                            <form method="POST" action="{{ route('staff.scholars.reject', $request) }}" style="display:inline" onsubmit="return confirm('Reject and permanently delete this account? This cannot be undone.');">
                                @csrf
                                <button type="submit" class="staff-action-btn" title="Reject and delete">✕</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No approval requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $requests->links() }}</div>
</div>

@endsection

@push('styles')
<style>.staff-muted{color:#6b7280;font-size:13px}.staff-stat-icon.red{background:#fee2e2}</style>
@endpush
