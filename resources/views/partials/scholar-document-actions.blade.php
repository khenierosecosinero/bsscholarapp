<div class="doc-actions">
    @if($doc->hasFile())
        <div class="doc-action-links">
            <a href="{{ route('user.documents.view', $doc) }}" class="btn small" target="_blank" rel="noopener">View</a>
            <a href="{{ route('user.documents.download', $doc) }}" class="btn small">Download</a>
        </div>
    @endif
    @if($doc->status === 'rejected')
        @include('partials.document-replacement-form', ['doc' => $doc])
        @if($doc->hasFile() && $doc->documentType->acceptsSubmissions())
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
    @elseif($doc->documentType->acceptsSubmissions() && $doc->status !== 'approved')
        @php
            $submissionAction = $doc->hasFile() ? 'Replace' : 'Upload';
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
