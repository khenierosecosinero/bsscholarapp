@extends('layouts.staff')

@section('page-content')

<section class="staff-stat-grid staff-stat-grid-4">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">👥</div><div class="staff-stat-body"><h3>Total Scholars</h3><div class="value">{{ $stats['total_scholars'] }}</div><div class="sub">Active: {{ $stats['active_scholars'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Active Scholars</h3><div class="value">{{ $stats['active_scholars'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Approval</h3><div class="value">{{ $stats['pending_scholars'] }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon purple">📄</div><div class="staff-stat-body"><h3>Pending Documents</h3><div class="value">{{ $stats['pending_documents'] }}</div></div></div>
</section>

<form method="GET" class="staff-filter-bar">
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by name, scholar ID, or email...">
    </div>
    <button type="submit" class="staff-btn">Search</button>
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table staff-scholars-table">
            <thead>
                <tr>
                    <th>Scholar Information</th>
                    <th>Scholar ID</th>
                    <th>Municipality / City</th>
                    <th>Province</th>
                    <th>School</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scholars as $scholar)
                    <tr>
                        <td data-label="Scholar Information">
                            <div class="staff-scholar-cell">
                                <x-user-avatar :user="$scholar" class="staff-scholar-avatar" />
                                <div class="staff-scholar-meta">
                                    <strong>{{ $scholar->full_name }}</strong>
                                    <small>{{ $scholar->email }}</small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Scholar ID">{{ $scholar->scholar_id }}</td>
                        <td data-label="Municipality / City">{{ $scholar->municipalityName() ?? '—' }}</td>
                        <td data-label="Province">{{ $scholar->provinceName() ?? '—' }}</td>
                        <td data-label="School">{{ $scholar->school_university ?? '—' }}</td>
                        <td data-label="Status"><span class="staff-badge {{ $scholar->status === 'approved' ? 'green' : ($scholar->status === 'pending' ? 'orange' : 'red') }}">{{ ucfirst($scholar->status) }}</span></td>
                        <td data-label="Actions"><a href="{{ route('staff.scholars.show', $scholar) }}" class="staff-action-btn" title="View">👁</a></td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="7">No scholars found for this location.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $scholars->links() }}</div>
</div>

@endsection
