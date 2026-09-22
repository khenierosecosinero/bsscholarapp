@extends('layouts.admin')

@section('page-content')

@php
    $backQuery = array_filter([
        'location' => (($locationKey ?? 'all') !== 'all') ? $locationKey : null,
        'program_type' => (($programType ?? 'all') !== 'all') ? $programType : null,
    ]);
    $submittedCount = $documents->filter(fn ($document) => $document->hasFile())->count();
@endphp

<div class="admin-scholar-documents">
    <div class="staff-card admin-scholar-documents-header">
        <div class="staff-scholar-cell">
            <x-user-avatar :user="$scholar" class="staff-scholar-avatar admin-scholar-profile-avatar" />
            <div class="staff-scholar-meta">
                <strong>{{ $scholar->full_name }}</strong>
                <small>{{ $scholar->scholar_id }}</small>
            </div>
        </div>
        <div class="admin-scholar-fields" style="margin-top:16px">
            <div class="admin-scholar-field">
                <span>Scholar Program</span>
                <strong>{{ $scholar->scholarshipProgram?->programLabel() ?? '—' }}</strong>
            </div>
            <div class="admin-scholar-field">
                <span>Program Type</span>
                <strong>{{ $scholar->scholarshipProgram?->programTypeLabel() ?? '—' }}</strong>
            </div>
            <div class="admin-scholar-field">
                <span>Submitted Files</span>
                <strong>{{ $submittedCount }} of {{ $documents->count() }}</strong>
            </div>
        </div>
        <p class="staff-muted admin-scholar-profile-note">View-only document submissions. Approval and rejection remain on Scholar Staff.</p>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h2>Submitted Documents</h2>
            <span class="staff-muted">{{ $documents->count() }} {{ \Illuminate\Support\Str::plural('record', $documents->count()) }}</span>
        </div>
        <div class="staff-table-wrap">
            <table class="staff-table staff-stack-table">
                <thead>
                    <tr>
                        <th>Document Type</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>File</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td data-label="Document Type">
                                <strong>{{ $document->documentType?->name ?? 'Document' }}</strong>
                                @if($document->hasFile())
                                    <span class="admin-document-file-name">{{ $document->original_name ?? 'Attached file' }}</span>
                                @endif
                            </td>
                            <td data-label="Submitted">
                                {{ $document->uploaded_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}
                            </td>
                            <td data-label="Status">
                                <span class="staff-badge {{ $document->reviewBadgeClass() }}">{{ ucfirst(str_replace('_', ' ', $document->reviewStatus())) }}</span>
                                @if($document->status === 'rejected' && $document->review_notes)
                                    <div class="staff-muted" style="margin-top:4px">{{ $document->review_notes }}</div>
                                @endif
                            </td>
                            <td data-label="File">
                                @if($document->hasFile())
                                    <div class="staff-action-group">
                                        <a href="{{ route('admin.documents.view', $document) }}" class="staff-btn staff-btn-sm" target="_blank" rel="noopener">Preview</a>
                                        <a href="{{ route('admin.documents.download', $document) }}" class="staff-btn staff-btn-sm">Download</a>
                                    </div>
                                @else
                                    <span class="staff-muted">No file submitted</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="staff-table-empty"><td colspan="4">This scholar has no document records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <a href="{{ route('admin.documents', $backQuery) }}" class="staff-card-link admin-scholar-back">&larr; Back to documents list</a>
</div>

@endsection
