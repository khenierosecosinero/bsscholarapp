@extends('layouts.admin')

@section('page-content')

@include('partials.admin-academic-year-selector', ['inputId' => 'admin-scholars-year', 'compact' => true])

@include('partials.admin-scholars-filter')

<div class="staff-card">
    <div class="staff-table-wrap admin-data-table-wrap">
        <table class="staff-table staff-manage-table admin-data-table admin-scholars-table">
                <thead>
                    <tr>
                        <th>Scholar</th>
                        <th>Scholar ID</th>
                        <th>Scholarship Club</th>
                        <th>Location</th>
                        <th>Course</th>
                        <th>School</th>
                        <th class="staff-col-status">Status</th>
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
                        <td data-label="Scholarship Club">{{ $scholar->scholarshipClub?->name ?: '—' }}</td>
                        <td data-label="Location">{{ $scholar->homeAddress() !== '' ? $scholar->homeAddress() : '—' }}</td>
                        <td data-label="Course">{{ filled($scholar->course_year_level) ? $scholar->course_year_level : '—' }}</td>
                        <td data-label="School">{{ $scholar->school_university ?? '—' }}</td>
                        <td data-label="Status" class="staff-col-status"><span class="staff-badge {{ $scholar->staffStatusBadgeClass() }}">{{ $scholar->accountStatusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="7">No scholars found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $scholars->links() }}</div>
</div>

@endsection
