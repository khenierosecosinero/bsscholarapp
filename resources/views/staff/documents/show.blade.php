@extends('layouts.staff')

@section('page-content')

<div class="staff-card" style="margin-bottom:20px">
    <div class="staff-card-header">
        <h2>{{ $documentType->name }}</h2>
        <a href="{{ route('staff.documents') }}" class="staff-card-link">&larr; Back to Documents</a>
    </div>
    <p class="staff-muted" style="margin:0 0 12px">{{ $documentType->description ?: 'No description provided.' }}</p>
    <div class="staff-doc-type-counts" style="margin-bottom:12px">
        <span>{{ $counts['submitted'] }} submitted</span>
        <span class="pending">{{ $counts['pending'] }} pending</span>
        <span class="approved">{{ $counts['approved'] }} approved</span>
        <span class="rejected">{{ $counts['rejected'] }} rejected</span>
        <span>{{ $counts['not_submitted'] }} not submitted</span>
    </div>
    <div class="staff-doc-type-actions" style="padding:0;border:0">
        <a href="{{ route('staff.documents.edit', $documentType) }}" class="staff-btn staff-btn-sm">Edit type</a>
        <form method="POST" action="{{ route('staff.documents.destroy', $documentType) }}" onsubmit="return confirm('Remove {{ addslashes($documentType->name) }}? Scholar files for this type will also be deleted.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Delete type</button>
        </form>
    </div>
</div>

<form method="GET" class="staff-filter-bar">
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
</form>

<div class="staff-card">
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Scholar</th>
                    <th>File</th>
                    <th>Date Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td>
                            <div class="staff-scholar-cell">
                                <div class="staff-scholar-avatar">{{ strtoupper(substr($document->user?->full_name ?? 'S', 0, 1)) }}</div>
                                <div class="staff-scholar-meta">
                                    <strong>{{ $document->user?->full_name ?? '—' }}</strong>
                                    <small>{{ $document->user?->scholar_id }} · {{ $document->user?->locationLabel() }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($document->hasFile())
                                {{ $document->original_name ?? 'Attached file' }}
                            @else
                                <span class="staff-muted">No file attached</span>
                            @endif
                        </td>
                        <td>{{ $document->uploaded_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}</td>
                        <td>
                            <span class="staff-badge {{ $document->reviewBadgeClass() }}">{{ ucfirst(str_replace('_', ' ', $document->reviewStatus())) }}</span>
                            @if($document->status === 'rejected' && $document->review_notes)
                                <div class="staff-muted" style="margin-top:4px">{{ $document->review_notes }}</div>
                            @endif
                        </td>
                        <td>
                            @if($document->hasFile())
                                <div class="staff-doc-review-actions">
                                    <a href="{{ route('staff.documents.view', $document) }}" class="staff-btn staff-btn-sm" target="_blank" rel="noopener">Review</a>
                                    <a href="{{ route('staff.documents.download', $document) }}" class="staff-btn staff-btn-sm">Download</a>

                                    @if($document->reviewStatus() !== 'approved')
                                        <form method="POST" action="{{ route('staff.documents.status', $document) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="staff-btn staff-btn-sm staff-btn-success">Approve</button>
                                        </form>
                                    @endif

                                    @if($document->reviewStatus() !== 'rejected')
                                        <form method="POST" action="{{ route('staff.documents.status', $document) }}" class="staff-reject-form" onsubmit="return confirm('Reject this {{ addslashes($documentType->name) }}?')">
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
                                <span class="staff-muted">Waiting for upload</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            @if($statusFilter === 'submitted')
                                No scholars have submitted {{ $documentType->name }} yet.
                            @else
                                No matching scholars for this filter.
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
