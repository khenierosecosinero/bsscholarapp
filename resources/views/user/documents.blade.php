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
                <strong class="doc-name">{{ $doc->documentType->name }}</strong>
                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                <span class="doc-date doc-uploaded">{{ $doc->uploaded_at ? 'Uploaded on ' . $doc->uploaded_at->format('M d Y') : 'Not yet uploaded' }}</span>
                <span class="doc-date">Deadline: {{ $doc->documentType->deadlineLabel() }}</span>
                @if($doc->status === 'rejected' && $doc->review_notes)
                    <span class="muted">Reason: {{ $doc->review_notes }}</span>
                @elseif($doc->status === 'pending' && $doc->latestRejectionNote())
                    <span class="muted">Previous rejection: {{ $doc->latestRejectionNote() }}</span>
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
                <div class="tabs" role="tablist">
                    @foreach(['all' => 'All Documents', 'submitted' => 'Submitted', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'not_submitted' => 'Not Submitted'] as $key => $label)
                        <a href="{{ route('user.documents', ['tab' => $key]) }}" class="tab {{ $activeTab === $key ? 'active' : '' }}" role="tab" @if($activeTab === $key) aria-selected="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            @php
                $filtered = match ($activeTab) {
                    'submitted' => $documents->filter(fn ($doc) => $doc->hasFile()),
                    'pending' => $documents->whereIn('status', ['pending', 'submitted']),
                    'approved' => $documents->where('status', 'approved'),
                    'rejected' => $documents->where('status', 'rejected'),
                    'not_submitted' => $documents->where('status', 'not_submitted'),
                    default => $documents,
                };
                $documentsEmptyMessage = $documentTypes->isEmpty()
                    ? 'No required documents have been posted by Scholar Staff yet.'
                    : 'No documents found.';
            @endphp
            <div class="doc-mobile-list">
                @forelse($filtered as $doc)
                    @include('partials.scholar-document-entry', ['presentation' => 'card'])
                @empty
                    <p class="doc-mobile-empty muted">{{ $documentsEmptyMessage }}</p>
                @endforelse
            </div>
            <div class="table-wrap doc-table-wrap">
            <table class="table doc-table">
                <thead><tr><th>Document Type</th><th>Description</th><th>Status</th><th>Deadline</th><th>Date Uploaded</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($filtered as $doc)
                        @include('partials.scholar-document-entry', ['presentation' => 'row'])
                    @empty
                        <tr><td colspan="6" class="muted center">{{ $documentsEmptyMessage }}</td></tr>
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

@push('scripts')
<script>
document.querySelectorAll('.doc-replace-form').forEach(function (form) {
    var input = form.querySelector('input[type="file"]');
    var filename = form.querySelector('.doc-replace-filename');
    var error = form.querySelector('.doc-replace-error');
    var button = form.querySelector('button[type="submit"]');
    var allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    var maxBytes = 5 * 1024 * 1024;

    function showError(message) {
        error.hidden = !message;
        error.textContent = message || '';
    }

    input.addEventListener('change', function () {
        var file = input.files[0];
        var message = '';
        filename.textContent = file ? file.name : 'No file selected';
        filename.title = file ? file.name : 'No file selected';
        button.disabled = true;
        if (file) {
            var extension = file.name.split('.').pop().toLowerCase();
            if (!allowed.includes(extension)) {
                message = 'Use a PDF, JPG, or PNG file.';
            } else if (file.size > maxBytes) {
                message = 'File size must not exceed 5MB.';
            }
        }
        showError(message);
        form.classList.toggle('has-file', Boolean(file) && !message);
        form.classList.toggle('has-error', Boolean(message));
        if (file && !message) {
            button.disabled = false;
        }
    });

    form.addEventListener('submit', function (event) {
        if (form.dataset.submitting === '1' || button.disabled) {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        button.disabled = true;
    });
});
</script>
@endpush
