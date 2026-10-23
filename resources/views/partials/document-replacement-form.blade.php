<form method="POST" action="{{ route('user.documents.upload') }}" enctype="multipart/form-data" class="doc-replace-form">
    @csrf
    <input type="hidden" name="document_type_id" value="{{ $doc->document_type_id }}">
    <div class="doc-replace-picker">
        <label class="doc-replace-browse">
            <input type="file" name="file" class="doc-replace-input" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
            <svg class="doc-replace-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21.44 11.05l-8.49 8.49a5.5 5.5 0 0 1-7.78-7.78l8.49-8.48a3.5 3.5 0 0 1 4.95 4.95l-8.49 8.48a1.5 1.5 0 0 1-2.12-2.12l7.78-7.78"/>
            </svg>
            <span>Browse Files</span>
        </label>
        <span class="doc-replace-filename" title="No file selected">No file selected</span>
    </div>
    <p class="doc-replace-error" hidden></p>
    <button type="submit" class="btn small doc-replace-submit" disabled>Upload Replacement</button>
</form>
