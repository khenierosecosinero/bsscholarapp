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
        <table class="staff-table staff-stack-table staff-scholars-table">
            <thead>
                <tr>
                    <th>Scholar Information</th>
                    <th>Scholar ID</th>
                    <th>Municipality / City</th>
                    <th>Province</th>
                    <th>School</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scholars as $scholar)
                    <tr>
                        <td data-label="Scholar Information">
                            <div class="staff-stack-value">
                                <div class="staff-scholar-cell">
                                    <x-user-avatar :user="$scholar" class="staff-scholar-avatar" />
                                    <div class="staff-scholar-meta">
                                        <div class="staff-scholar-name-row">
                                            <a href="{{ route('staff.scholars.show', $scholar) }}" class="staff-scholar-name-link" aria-label="View information for {{ $scholar->full_name }}">{{ $scholar->full_name }}</a>
                                            @include('partials.staff-scholar-presence', ['scholar' => $scholar])
                                        </div>
                                        <small>{{ $scholar->email }}</small>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Scholar ID"><div class="staff-stack-value">{{ $scholar->scholar_id }}</div></td>
                        <td data-label="Municipality / City"><div class="staff-stack-value">{{ $scholar->municipalityName() ?? '—' }}</div></td>
                        <td data-label="Province"><div class="staff-stack-value">{{ $scholar->provinceName() ?? '—' }}</div></td>
                        <td data-label="School"><div class="staff-stack-value">{{ $scholar->school_university ?? '—' }}</div></td>
                        <td data-label="Status"><div class="staff-stack-value"><span class="staff-badge {{ $scholar->status === 'approved' ? 'green' : ($scholar->status === 'pending' ? 'orange' : 'red') }}">{{ ucfirst($scholar->status) }}</span></div></td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="6">No scholars found for this location.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $scholars->links() }}</div>
</div>

<div hidden id="staff-scholar-presence-root" data-presence-url="{{ route('staff.scholars.presence') }}"></div>

@endsection
