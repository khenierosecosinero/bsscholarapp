@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')

<form method="GET" class="staff-filter-bar">
    <input type="hidden" name="location" value="{{ $locationKey }}">
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by name or email...">
    </div>
    <button type="submit" class="staff-btn staff-btn-primary">Search</button>
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Email</th>
                    <th>Assigned Program</th>
                    <th>Status</th>
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
                                </div>
                            </div>
                        </td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->scholarshipProgram?->programLabel() ?? 'Unassigned' }}</td>
                        <td><span class="staff-badge green">{{ ucfirst($member->status ?? 'approved') }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4">No scholar staff found for this scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $staffMembers->links() }}</div>
</div>

@endsection
