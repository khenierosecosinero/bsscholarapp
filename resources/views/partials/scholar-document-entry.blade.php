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
    $uploadedLabel = $doc->uploaded_at?->format('M d Y') ?? '—';
    $deadlineLabel = $doc->documentType->deadlineLabel();
@endphp

@if(($presentation ?? 'row') === 'card')
    <article class="doc-mobile-card">
        <div class="doc-mobile-row">
            <span class="doc-mobile-label">Document Type</span>
            <div class="doc-mobile-value">
                <div class="table-event">@include('partials.document-icon', ['slug' => $doc->documentType->slug, 'size' => 'sm'])<span class="doc-name">{{ $doc->documentType->name }}</span></div>
            </div>
        </div>
        <div class="doc-mobile-row">
            <span class="doc-mobile-label">Description</span>
            <div class="doc-mobile-value">{{ $doc->documentType->description }}</div>
        </div>
        <div class="doc-mobile-row">
            <span class="doc-mobile-label">Status</span>
            <div class="doc-mobile-value">
                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                @if($doc->status === 'rejected' && $doc->review_notes)
                    <div class="doc-review-note">Reason: {{ $doc->review_notes }}</div>
                @elseif($doc->status === 'pending' && $doc->latestRejectionNote())
                    <div class="doc-review-note">Previous rejection: {{ $doc->latestRejectionNote() }}</div>
                @endif
            </div>
        </div>
        <div class="doc-mobile-row">
            <span class="doc-mobile-label">Deadline</span>
            <div class="doc-mobile-value doc-date">{{ $deadlineLabel }}</div>
        </div>
        <div class="doc-mobile-row">
            <span class="doc-mobile-label">Date Uploaded</span>
            <div class="doc-mobile-value doc-date">{{ $uploadedLabel }}</div>
        </div>
        <div class="doc-mobile-row">
            <span class="doc-mobile-label">Action</span>
            <div class="doc-mobile-value">
                @include('partials.scholar-document-actions')
            </div>
        </div>
    </article>
@else
    <tr>
        <td data-label="Document Type"><div class="table-event">@include('partials.document-icon', ['slug' => $doc->documentType->slug, 'size' => 'sm'])<span class="doc-name">{{ $doc->documentType->name }}</span></div></td>
        <td data-label="Description">{{ $doc->documentType->description }}</td>
        <td data-label="Status">
            <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
            @if($doc->status === 'rejected' && $doc->review_notes)
                <div class="doc-review-note">Reason: {{ $doc->review_notes }}</div>
            @elseif($doc->status === 'pending' && $doc->latestRejectionNote())
                <div class="doc-review-note">Previous rejection: {{ $doc->latestRejectionNote() }}</div>
            @endif
        </td>
        <td class="doc-date" data-label="Deadline">{{ $deadlineLabel }}</td>
        <td class="doc-date" data-label="Date Uploaded">{{ $uploadedLabel }}</td>
        <td data-label="Action">
            @include('partials.scholar-document-actions')
        </td>
    </tr>
@endif
