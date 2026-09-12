@extends('layouts.staff')

@section('page-content')

<section class="staff-stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
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
        <table class="staff-table">
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
                        <td>
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($scholar->full_name, 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $scholar->full_name }}</strong>
                                    <small>{{ $scholar->email }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $scholar->scholar_id }}</td>
                        <td>{{ $scholar->municipalityName() ?? '—' }}</td>
                        <td>{{ $scholar->provinceName() ?? '—' }}</td>
                        <td>{{ $scholar->school_university ?? '—' }}</td>
                        <td><span class="staff-badge {{ $scholar->status === 'approved' ? 'green' : ($scholar->status === 'pending' ? 'orange' : 'red') }}">{{ ucfirst($scholar->status) }}</span></td>
                        <td><a href="{{ route('staff.scholars.show', $scholar) }}" class="staff-action-btn" title="View">👁</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No scholars found for this location.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $scholars->links() }}</div>
</div>

@endsection
