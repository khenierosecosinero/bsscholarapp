@extends('layouts.staff')

@section('page-content')

<div class="staff-card" style="margin-bottom:20px">
    <div class="staff-card-header staff-doc-detail-heading">
        <a href="{{ route('staff.documents') }}" class="staff-card-link staff-doc-back-link">&larr; Back to Documents</a>
        <h2>{{ $documentType->name }}</h2>
    </div>
    <p class="staff-muted" style="margin:0 0 12px">{{ $documentType->description ?: 'No description provided.' }}</p>
    <div class="staff-doc-type-counts" style="margin-bottom:12px">
        <span>{{ $counts['submitted'] }} submitted</span>
        <span class="pending">{{ $counts['pending'] }} pending</span>
        <span class="approved">{{ $counts['approved'] }} approved</span>
        <span class="rejected">{{ $counts['rejected'] }} rejected</span>
        <span>{{ $counts['not_submitted'] }} not submitted</span>
    </div>
    <section class="staff-submission-session {{ $documentType->submissionsAreOpen() ? 'is-open' : 'is-closed' }}">
        <div class="staff-submission-session-status">
            <span class="staff-badge {{ $documentType->submissionStatusBadgeClass() }} staff-submission-status-badge">{{ $documentType->submissionStatusLabel() }}</span>
            <div>
                <strong>Document submission</strong>
                <p class="staff-muted" style="margin:4px 0 0">
                    Deadline: {{ $documentType->deadlineLabel() }}.
                    @if($documentType->submissionsAreOpen())
                        Scholars can upload and replace {{ $documentType->name }} while submissions are open and the deadline has not passed.
                        @if($documentType->submissions_opened_at)
                            Opened {{ $documentType->submissions_opened_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.
                        @endif
                    @else
                        Scholars cannot upload, replace, or resubmit {{ $documentType->name }}. Existing files and review statuses stay unchanged.
                        @if($documentType->submissions_closed_at)
                            Closed {{ $documentType->submissions_closed_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.
                        @endif
                    @endif
                </p>
            </div>
        </div>
        <div class="staff-submission-session-actions">
            <form method="POST" action="{{ route('staff.documents.submissions.open', $documentType) }}">
                @csrf
                <button type="submit" class="staff-btn staff-btn-success" @disabled($documentType->submissionsAreOpen())>Open Submissions</button>
            </form>
            <form
                method="POST"
                action="{{ route('staff.documents.submissions.close', $documentType) }}"
                data-confirm="Scholars will not be able to upload, replace, or resubmit {{ $documentType->name }}. Existing files and review statuses will stay as they are."
                data-confirm-title="Close submissions for {{ $documentType->name }}?"
                data-confirm-yes="Close Submission"
                data-confirm-no="Cancel"
                data-confirm-variant="danger"
            >
                @csrf
                <button type="submit" class="staff-btn staff-btn-danger" @disabled(! $documentType->submissionsAreOpen())>Close Submissions</button>
            </form>
        </div>
    </section>
    <div class="staff-doc-type-actions" style="padding:0;border:0">
        <a href="{{ route('staff.documents.edit', $documentType) }}" class="staff-btn staff-btn-sm">Edit type</a>
        <form
            method="POST"
            action="{{ route('staff.documents.destroy', $documentType) }}"
            data-confirm="Scholar files for this type will also be deleted."
            data-confirm-title="Remove {{ $documentType->name }}?"
            data-confirm-name="{{ $documentType->name }}"
            data-confirm-note="This cannot be undone."
            data-confirm-yes="Remove"
            data-confirm-no="Cancel"
            data-confirm-variant="danger"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Delete type</button>
        </form>
    </div>
</div>

<form method="GET" class="staff-filter-bar staff-document-school-filter">
    <div class="staff-report-period-field">
        <label for="document-school">School/University</label>
        <select id="document-school" name="school" class="staff-select" onchange="this.form.submit()">
            @foreach($schoolOptions as $option)
                <option value="{{ $option['key'] }}" @selected($selectedSchoolKey === $option['key'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="staff-report-period-field">
        <label for="document-type-filter">Document Type</label>
        <select id="document-type-filter" class="staff-select" onchange="if (this.value) window.location = this.value">
            @foreach($programTypes as $type)
                <option value="{{ route('staff.documents.show', array_filter(['documentType' => $type->id, 'search' => $search ?: null, 'status' => $statusFilter, 'school' => $selectedSchoolKey !== 'all' ? $selectedSchoolKey : null])) }}" @selected($type->id === $documentType->id)>{{ $type->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by scholar name or ID...">
    </div>
    <select name="status" class="staff-select" onchange="this.form.submit()">
        <option value="submitted" @selected($statusFilter === 'submitted')>Submitted ({{ $counts['submitted'] }})</option>
        <option value="pending" @selected($statusFilter === 'pending')>Pending ({{ $counts['pending'] }})</option>
        <option value="approved" @selected($statusFilter === 'approved')>Approved ({{ $counts['approved'] }})</option>
        <option value="rejected" @selected($statusFilter === 'rejected')>Rejected ({{ $counts['rejected'] }})</option>
        <option value="not_submitted" @selected($statusFilter === 'not_submitted')>Not submitted ({{ $counts['not_submitted'] }})</option>
        <option value="all" @selected($statusFilter === 'all')>All scholars</option>
    </select>
    <button type="submit" class="staff-btn">Filter</button>
    @if($selectedSchoolKey !== 'all')
        <a href="{{ route('staff.documents.show', array_filter(['documentType' => $documentType->id, 'search' => $search ?: null, 'status' => $statusFilter])) }}" class="staff-btn">Reset</a>
    @endif
</form>

<div class="staff-card">
    <div class="staff-table-wrap staff-data-table-wrap">
        <table class="staff-table staff-manage-table staff-documents-table staff-data-table">
            <colgroup>
                <col class="staff-documents-col-scholar">
                <col class="staff-documents-col-file">
                <col class="staff-documents-col-date">
                <col class="staff-documents-col-status">
                <col class="staff-documents-col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th>Scholar Information</th>
                    <th>File</th>
                    <th>Date Submitted</th>
                    <th class="staff-col-status">Status</th>
                    <th class="staff-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td data-label="Scholar Information">
                            <div class="staff-stack-value">
                                <div class="staff-scholar-cell">
                                    <x-user-avatar :user="$document->user" class="staff-scholar-avatar" />
                                    <div class="staff-scholar-meta">
                                        <strong>{{ $document->user?->full_name ?? '—' }}</strong>
                                        <small>{{ $document->user?->scholar_id }} · {{ $document->user?->scholarshipClubLabel() }}</small>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="File" class="staff-col-file">
                            <div class="staff-stack-value">
                                @if($document->hasFile())
                                    {{ $document->original_name ?? 'Attached file' }}
                                @else
                                    <span class="staff-muted">No file attached</span>
                                @endif
                            </div>
                        </td>
                        <td data-label="Date Submitted" class="staff-col-date">
                            <div class="staff-stack-value">{{ $document->uploaded_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}</div>
                        </td>
                        <td data-label="Status" class="staff-col-status">
                            <div class="staff-stack-value staff-doc-status">
                                <span class="staff-badge {{ $document->reviewBadgeClass() }}">{{ ucfirst(str_replace('_', ' ', $document->reviewStatus())) }}</span>
                                @if($document->status === 'rejected' && $document->review_notes)
                                    <div class="staff-muted staff-doc-status-note">{{ $document->review_notes }}</div>
                                @endif
                            </div>
                        </td>
                        <td data-label="Actions" class="staff-col-actions">
                            @if($document->hasFile())
                                <div class="staff-stack-value staff-doc-review-actions">
                                    <a href="{{ route('staff.documents.view', $document) }}" class="staff-btn staff-btn-sm" target="_blank" rel="noopener">Review</a>
                                    <a href="{{ route('staff.documents.download', $document) }}" class="staff-btn staff-btn-sm">Download</a>

                                    @if($document->reviewStatus() !== 'approved')
                                        <form
                                            method="POST"
                                            action="{{ route('staff.documents.status', $document) }}"
                                            data-confirm="This document will be marked as approved."
                                            data-confirm-title="Approve this {{ $documentType->name }}?"
                                            data-confirm-yes="Approve"
                                            data-confirm-no="Cancel"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="staff-btn staff-btn-sm staff-btn-success">Approve</button>
                                        </form>
                                    @endif

                                    @if($document->reviewStatus() !== 'rejected')
                                        <form
                                            method="POST"
                                            action="{{ route('staff.documents.status', $document) }}"
                                            class="staff-reject-form"
                                            data-confirm="The scholar can upload a replacement after rejection."
                                            data-confirm-title="Reject this {{ $documentType->name }}?"
                                            data-confirm-yes="Reject"
                                            data-confirm-no="Cancel"
                                            data-confirm-variant="danger"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="text" name="review_notes" class="staff-review-notes" placeholder="Reason (optional)" maxlength="500">
                                            <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Reject</button>
                                        </form>
                                    @endif

                                    @if($document->reviewStatus() !== 'pending')
                                        <form method="POST" action="{{ route('staff.documents.status', $document) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="pending">
                                            <button type="submit" class="staff-btn staff-btn-sm staff-btn-warning">Set pending</button>
                                        </form>
                                    @endif
                                </div>
                            @else
                                <div class="staff-stack-value"><span class="staff-muted">Waiting for upload</span></div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="staff-table-empty">
                        <td colspan="5">
                            @if($selectedSchoolKey !== 'all' || $search !== '' || $statusFilter !== 'submitted')
                                No matching scholars for this filter.
                            @else
                                No scholars have submitted {{ $documentType->name }} yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="staff-pagination">{{ $documents->links() }}</div>
</div>

@endsection
