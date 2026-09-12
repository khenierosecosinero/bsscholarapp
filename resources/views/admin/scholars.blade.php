@extends('layouts.admin')

@section('page-content')

@include('partials.admin-location-filter')
@include('partials.admin-scope-banner')

<form method="GET" class="staff-filter-bar">
    @include('partials.admin-scope-fields')
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by name, scholar ID, or email...">
    </div>
    <button type="submit" class="staff-btn staff-btn-primary">Search</button>
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Scholar ID</th>
                    <th>Scholar Program</th>
                    <th>Program Type</th>
                    <th>School</th>
                    <th>Status</th>
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
                        <td>{{ $scholar->scholarshipProgram?->programLabel() ?? '—' }}</td>
                        <td>{{ $scholar->scholarshipProgram?->programTypeLabel() ?? '—' }}</td>
                        <td>{{ $scholar->school_university ?? '—' }}</td>
                        <td><span class="staff-badge {{ $scholar->status === 'approved' ? 'green' : ($scholar->status === 'pending' ? 'orange' : 'red') }}">{{ ucfirst($scholar->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No scholars found for this scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $scholars->links() }}</div>
</div>

@endsection
