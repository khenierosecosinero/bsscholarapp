@extends('layouts.user')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/documents-page.css') }}">
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
        @endphp
        <div class="doc-status-card {{ $doc->status }}">
            @include('partials.document-icon', ['slug' => $doc->documentType->slug, 'size' => 'lg'])
            <div class="doc-status-body">
                <strong>{{ $doc->documentType->name }}</strong>
                <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $doc->status)) }}</span>
                <span class="muted">{{ $doc->uploaded_at ? 'Uploaded on ' . $doc->uploaded_at->format('M d, Y') : 'Not yet uploaded' }}</span>
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
        <div class="card">
            <div class="tabs-row">
                <div class="tabs">
                    @foreach(['all' => 'All Documents', 'submitted' => 'Submitted', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'not_submitted' => 'Not Submitted'] as $key => $label)
                        <a href="{{ route('user.documents', ['tab' => $key]) }}" class="tab {{ $activeTab === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <div class="table-wrap">
            <table class="table doc-table">
                <thead><tr><th>Document Type</th><th>Description</th><th>Status</th><th>Date Uploaded</th><th>Action</th></tr></thead>
                <tbody>
                    @php
                        $filtered = match ($activeTab) {
                            'submitted' => $documents->filter(fn ($doc) => filled($doc->file_path)),
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
                        @endphp
                        <tr>
                            <td><div class="table-event">@include('partials.document-icon', ['slug' => $doc->documentType->slug, 'size' => 'sm'])<span>{{ $doc->documentType->name }}</span></div></td>
                            <td>{{ $doc->documentType->description }}</td>
                            <td><span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $doc->status)) }}</span></td>
                            <td>{{ $doc->uploaded_at?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                @if($doc->file_path)
                                    <a href="{{ route('user.documents.download', $doc) }}" class="icon-btn" title="Download">&#11015;</a>
                                @endif
                                @if($doc->status !== 'approved')
                                    <button type="button" class="btn small" onclick="document.getElementById('doc-type-select').value='{{ $doc->document_type_id }}';document.getElementById('file-input').click()">{{ $doc->file_path ? 'Replace' : 'Upload' }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted center">{{ $documentTypes->isEmpty() ? 'No required documents have been posted by Scholar Staff yet.' : 'No documents found.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <p class="table-footer muted">Showing {{ $filtered->count() }} document(s)</p>
        </div>

        <div class="info-banner">
            <span class="info-icon-lg">&#8505;</span>
            <div><strong>Why do we need these documents?</strong><p>These documents help us verify your identity, enrollment, and eligibility as a Batang Surigaonon Scholar.</p></div>
        </div>
    </div>

    <aside class="documents-sidebar">
        <div class="card documents-sidebar-card upload-card">
            <div class="card-header">UPLOAD NEW DOCUMENT</div>
            @if($documentTypes->isEmpty())
                <p class="muted" style="margin:0">Uploads will be available after Scholar Staff posts a required document.</p>
            @else
            <form method="POST" action="{{ route('user.documents.upload') }}" enctype="multipart/form-data" id="upload-form">
                @csrf
                <div class="upload-zone" onclick="document.getElementById('file-input').click()">
                    <div class="upload-icon">&#9729;</div>
                    <p>Drag and drop your file here<br>or click to browse</p>
                    <span id="file-name" class="muted small"></span>
                </div>
                <input type="file" name="file" id="file-input" accept=".pdf,.jpg,.jpeg,.png" hidden required>
                <select name="document_type_id" id="doc-type-select" class="form-select" required>
                    <option value="">Select Document Type</option>
                    @foreach($documentTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn blue full">Upload Document</button>
            </form>
            @endif
        </div>

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
