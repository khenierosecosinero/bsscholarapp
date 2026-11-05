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
    $deadline = $doc->documentType->submission_deadline?->timezone(config('app.timezone'));
    $uploadedAt = $doc->uploaded_at?->timezone(config('app.timezone'));
@endphp
<tr>
    <td data-label="Document Type">
        <div class="table-event">
            @include('partials.document-icon', ['slug' => $doc->documentType->slug, 'size' => 'sm'])
            <span class="doc-name">{{ $doc->documentType->name }}</span>
        </div>
    </td>
    <td data-label="Description">{{ $doc->documentType->description ?: '—' }}</td>
    <td data-label="Status">
        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
        @if($doc->status === 'rejected' && $doc->review_notes)
            <div class="doc-review-note">Reason: {{ $doc->review_notes }}</div>
        @elseif($doc->status === 'pending' && $doc->latestRejectionNote())
            <div class="doc-review-note">Previous rejection: {{ $doc->latestRejectionNote() }}</div>
        @endif
    </td>
    <td class="doc-date" data-label="Deadline">
        @if($deadline)
            <span class="doc-date-stack">
                <span>{{ $deadline->format('M j, Y') }}</span>
                <span>{{ $deadline->format('g:i A') }}</span>
            </span>
        @else
            No deadline set
        @endif
    </td>
    <td class="doc-date" data-label="Date Uploaded">
        @if($uploadedAt)
            <span class="doc-date-stack">
                <span>{{ $uploadedAt->format('M d') }}</span>
                <span>{{ $uploadedAt->format('Y') }}</span>
            </span>
        @else
            —
        @endif
    </td>
    <td data-label="Action">
        @include('partials.scholar-document-actions')
    </td>
</tr>
