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
    <select name="school" class="staff-select" aria-label="School / University" onchange="this.form.submit()">
        @foreach($schoolOptions as $option)
            <option value="{{ $option['key'] }}" @selected($selectedSchoolKey === $option['key'])>{{ $option['label'] }}</option>
        @endforeach
    </select>
    <button type="submit" class="staff-btn">Search</button>
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table staff-scholars-table staff-manage-table">
            <thead>
                <tr>
                    <th>Scholar Information</th>
                    <th>Scholar ID</th>
                    <th>No./Street</th>
                    <th>Municipality / City</th>
                    <th>Province</th>
                    <th>School</th>
                    <th class="staff-col-status">Status</th>
                    <th class="staff-col-actions">Actions</th>
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
                        <td data-label="No./Street"><div class="staff-stack-value">{{ $scholar->streetName() ?: '—' }}</div></td>
                        <td data-label="Municipality / City"><div class="staff-stack-value">{{ $scholar->municipalityName() ?: ($scholar->scholarshipClubCity() ?? '—') }}</div></td>
                        <td data-label="Province"><div class="staff-stack-value">{{ $scholar->provinceName() ?: ($scholar->scholarshipClubProvince() ?? '—') }}</div></td>
                        <td data-label="School"><div class="staff-stack-value">{{ $scholar->scholarshipClubSchool?->name ?: ($scholar->school_university ?: '—') }}</div></td>
                        <td data-label="Status" class="staff-col-status"><div class="staff-stack-value">@include('partials.account-suspension-status', ['account' => $scholar])</div></td>
                        <td data-label="Actions" class="staff-col-actions">
                            <div class="staff-stack-value">
                                @include('partials.account-access-actions', [
                                    'account' => $scholar,
                                    'blockUrl' => route('staff.scholars.block', $scholar),
                                    'suspendUrl' => route('staff.scholars.suspend', $scholar),
                                    'reactivateUrl' => route('staff.scholars.reactivate', $scholar),
                                ])
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="8">No scholars found for this Scholarship Club.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div hidden id="staff-scholar-presence-root" data-presence-url="{{ route('staff.scholars.presence') }}"></div>

@endsection
