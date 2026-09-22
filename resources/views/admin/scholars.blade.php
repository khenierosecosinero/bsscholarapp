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
        <table class="staff-table staff-stack-table">
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
                        <td data-label="Scholar">
                            <div class="staff-scholar-cell">
                                <x-user-avatar :user="$scholar" class="staff-scholar-avatar" />
                                <div class="staff-scholar-meta">
                                    <a href="{{ route('admin.scholars.show', $scholar) }}" class="admin-scholar-name-link" aria-label="View personal information for {{ $scholar->full_name }}">{{ $scholar->full_name }}</a>
                                    <small>{{ $scholar->email }}</small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Scholar ID">{{ $scholar->scholar_id }}</td>
                        <td data-label="Scholar Program">{{ $scholar->scholarshipProgram?->programLabel() ?? '—' }}</td>
                        <td data-label="Program Type">{{ $scholar->scholarshipProgram?->programTypeLabel() ?? '—' }}</td>
                        <td data-label="School">{{ $scholar->school_university ?? '—' }}</td>
                        <td data-label="Status"><span class="staff-badge {{ $scholar->status === 'approved' ? 'green' : ($scholar->status === 'pending' ? 'orange' : 'red') }}">{{ ucfirst($scholar->status) }}</span></td>
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
