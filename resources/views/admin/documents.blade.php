@extends('layouts.admin')

@section('page-content')

@php
    $documentsListQuery = array_filter([
        'region' => $selectedRegion ?: null,
        'location' => $locationKey ?? 'all',
        'club' => $selectedClubId ?: null,
        'search' => filled($search ?? '') ? $search : null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

@include('partials.admin-documents-filter')

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved</h3><div class="value">{{ $documentOverview['approved'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending</h3><div class="value">{{ $documentOverview['pending'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✕</div><div class="staff-stat-body"><h3>Rejected</h3><div class="value">{{ $documentOverview['rejected'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon blue">📄</div><div class="staff-stat-body"><h3>Total Records</h3><div class="value">{{ $documentOverview['total'] ?? 0 }}</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon purple">📋</div><div class="staff-stat-body"><h3>Document Types</h3><div class="value">{{ $documentTypesCount }}</div></div></div>
</section>

<div class="staff-card">
    <div class="staff-card-header"><h2>Document Submissions</h2></div>
    <div class="staff-table-wrap">
        <table class="staff-table staff-stack-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>Document Type</th>
                    <th>Scholarship Club</th>
                    <th>Submitted</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td data-label="Scholar">
                            @if($document->user)
                                <a href="{{ route('admin.documents.scholar', array_merge(['scholar' => $document->user], $documentsListQuery)) }}" class="admin-scholar-name-link" aria-label="View submitted documents for {{ $document->user->full_name }}">{{ $document->user->full_name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Document Type">{{ $document->documentType?->name ?? 'Document' }}</td>
                        <td data-label="Scholarship Club">{{ $document->user?->scholarshipClub?->name ?: '—' }}</td>
                        <td data-label="Submitted">{{ $document->created_at?->format('M j, Y') ?? '—' }}</td>
                        <td data-label="Status"><span class="staff-badge {{ $document->reviewBadgeClass() }}">{{ ucfirst(str_replace('_', ' ', $document->reviewStatus())) }}</span></td>
                    </tr>
                @empty
                    <tr class="staff-table-empty"><td colspan="5">No documents found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $documents->links() }}</div>
</div>

@endsection
