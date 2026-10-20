@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/documents-page.css') }}?v={{ filemtime(public_path('css/documents-page.css')) }}">
@endpush

@section('page-content')
<div class="page-documents">

@if(!empty($documentOverview))
    @php $o = $documentOverview; @endphp
    <section class="documents-overview-banner card">
        <div class="card-header">DOCUMENTS OVERVIEW</div>
        <div class="documents-overview-stats">
            <div class="documents-overview-stat"><span>Total Required</span><strong>{{ $o['total'] }}</strong></div>
            <div class="documents-overview-stat approved"><span>Approved</span><strong>{{ $o['approved'] }}</strong></div>
            <div class="documents-overview-stat pending"><span>Pending</span><strong>{{ $o['pending'] }}</strong></div>
            <div class="documents-overview-stat rejected"><span>Rejected</span><strong>{{ $o['rejected'] }}</strong></div>
            <div class="documents-overview-stat muted"><span>Not Submitted</span><strong>{{ $o['not_submitted'] }}</strong></div>
        </div>
    </section>
@endif

<section class="doc-status-grid">
    @forelse($documents as $doc)
        @php
            $badgeClass = match ($doc->status) {
                'approved' => 'confirmed',
                'submitted' => 'confirmed',
                'pending' => 'pending',
                'rejected' => 'rejected',
                'not_submitted' => 'muted',
                default => 'muted',
            };
            $statusLabel = match ($doc->status) {
                'approved' => 'Approved',
                'submitted' => 'Submitted',
                'pending' => 'Pending',
                'rejected' => 'Rejected',
                'not_submitted' => 'Not Submitted',
                default => ucfirst(str_replace('_', ' ', $doc->status)),
            };
        @endphp
        <div class="doc-status-card {{ $doc->status }}">
            @include('partials.document-icon', ['slug' => $doc->documentType->slug, 'size' => 'lg'])
            <div class="doc-status-body">
                <strong>{{ $doc->documentType->name }}</strong>
                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                <span class="muted">{{ $doc->uploaded_at ? 'Uploaded on ' . $doc->uploaded_at->format('M d Y') : 'Not yet uploaded' }}</span>
                <span class="muted">Deadline: {{ $doc->documentType->deadlineLabel() }}</span>
                @if($doc->status === 'rejected' && $doc->review_notes)
                    <span class="muted">Reason: {{ $doc->review_notes }}</span>
                @endif
            </div>
            @if($doc->status === 'approved' || $doc->status === 'submitted')
                <div class="doc-status-check green">&#10003;</div>
            @elseif($doc->status === 'pending')
                <div class="doc-status-check orange">&#128336;</div>
            @elseif($doc->status === 'rejected')
                <div class="doc-status-check red">✕</div>
            @elseif($doc->status === 'not_submitted')
                <div class="doc-status-check gray">—</div>
            @endif
        </div>
    @empty
        <div class="doc-empty-banner">
            <strong>No required documents yet</strong>
            <p class="muted">Scholar Staff has not posted any documents to submit. Check back after staff adds a requirement.</p>
        </div>
    @endforelse
</section>

<section class="documents-page-grid">
    <div class="documents-main">
        <form
            method="POST"
            action="{{ route('user.documents.upload') }}"
            enctype="multipart/form-data"
            id="upload-form"
            class="documents-table-upload"
        >
            @csrf
            <input type="hidden" name="document_type_id" id="doc-type-select" value="">
            <input type="file" name="file" id="file-input" accept=".pdf,.jpg,.jpeg,.png" tabindex="-1">
        </form>
        <div class="card">
            <div class="tabs-row">
                <div class="tabs">
                    @foreach(['all' => 'All Documents', 'submitted' => 'Submitted', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'not_submitted' => 'Not Submitted'] as $key => $label)
                        <a href="{{ route('user.documents', ['tab' => $key]) }}" class="tab {{ $activeTab === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <div class="table-wrap">
            <table class="table doc-table stack-table">
                <thead><tr><th>Document Type</th><th>Description</th><th>Status</th><th>Deadline</th><th>Date Uploaded</th><th>Action</th></tr></thead>
                <tbody>
                    @php
                        $filtered = match ($activeTab) {
                            'submitted' => $documents->filter(fn ($doc) => $doc->hasFile()),
                            'pending' => $documents->whereIn('status', ['pending', 'submitted']),
                            'approved' => $documents->where('status', 'approved'),
                            'rejected' => $documents->where('status', 'rejected'),
                            'not_submitted' => $documents->where('status', 'not_submitted'),
                            default => $documents,
                        };
                    @endphp
                    @forelse($filtered as $doc)
                        @php
                            $badgeClass = match ($doc->status) {
                                'approved' => 'confirmed',
                                'submitted' => 'confirmed',
                                'pending' => 'pending',
                                'rejected' => 'rejected',
                                'not_submitted' => 'muted',
                                default => 'muted',
                            };
                            $statusLabel = match ($doc->status) {
                                'approved' => 'Approved',
                                'submitted' => 'Submitted',
                                'pending' => 'Pending',
                                'rejected' => 'Rejected',
                                'not_submitted' => 'Not Submitted',
                                default => ucfirst(str_replace('_', ' ', $doc->status)),
                            };
                        @endphp
                        <tr>
                            <td data-label="Document Type"><div class="table-event">@include('partials.document-icon', ['slug' => $doc->documentType->slug, 'size' => 'sm'])<span>{{ $doc->documentType->name }}</span></div></td>
                            <td data-label="Description">{{ $doc->documentType->description }}</td>
                            <td data-label="Status"><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></td>
                            <td data-label="Deadline">{{ $doc->documentType->deadlineLabel() }}</td>
                            <td data-label="Date Uploaded">{{ $doc->uploaded_at?->format('M d Y') ?? '—' }}</td>
                            <td data-label="Action">
                                <div class="doc-actions">
                                    @if($doc->hasFile())
                                        <a href="{{ route('user.documents.view', $doc) }}" class="btn small" target="_blank" rel="noopener">View</a>
                                        <a href="{{ route('user.documents.download', $doc) }}" class="btn small">Download</a>
                                    @endif
                                    @if($doc->documentType->acceptsSubmissions() && $doc->status !== 'approved')
                                        @php
                                            $submissionAction = $doc->status === 'rejected' && $doc->hasFile()
                                                ? 'Resubmit'
                                                : ($doc->hasFile() ? 'Replace' : 'Upload');
                                        @endphp
                                        <button type="button" class="btn small" onclick="document.getElementById('doc-type-select').value='{{ $doc->document_type_id }}';document.getElementById('file-input').click()">{{ $submissionAction }}</button>
                                        @if($doc->hasFile())
                                            <form
                                                method="POST"
                                                action="{{ route('user.documents.destroy', $doc) }}"
                                                data-confirm="This file will be removed. You can upload a new one afterward."
                                                data-confirm-title="Delete this {{ $doc->documentType->name }}?"
                                                data-confirm-yes="Delete"
                                                data-confirm-no="Cancel"
                                                data-confirm-variant="danger"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn small danger">Delete</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted center">{{ $documentTypes->isEmpty() ? 'No required documents have been posted by Scholar Staff yet.' : 'No documents found.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <p class="table-footer muted">Showing {{ $filtered->count() }} document(s)</p>
        </div>

        @include('partials.bssa-alert', [
            'type' => 'info',
            'title' => 'Why do we need these documents?',
            'message' => 'These documents help us verify your identity, enrollment, and eligibility as a Batang Surigaonon Scholar.',
            'dismissible' => false,
            'autoDismiss' => false,
            'class' => 'bssa-alert--banner documents-info-card',
        ])
    </div>

    <aside class="documents-sidebar">
        <div class="card documents-sidebar-card guidelines-card">
            <div class="card-header">DOCUMENT GUIDELINES</div>
            <ul class="guidelines-list">
                <li><span class="check green">&#10003;</span> Ensure all documents are clear and readable</li>
                <li><span class="check green">&#10003;</span> Accepted formats: PDF, JPG, PNG</li>
                <li><span class="check green">&#10003;</span> File size must not exceed 5MB</li>
            </ul>
        </div>

        @include('partials.sidebar-help-card', [
            'helpText' => 'If you have questions about document requirements, you can visit our Help Center.',
        ])
    </aside>
</section>

</div>

@endsection
