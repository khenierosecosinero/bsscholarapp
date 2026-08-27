@php
    $helpText = $helpText ?? 'If you have questions, you can visit our Help Center.';
@endphp

<div class="card sidebar-page-card sidebar-help-card">
    <div class="card-header">NEED HELP?</div>
    <p class="sidebar-help-text">{{ $helpText }}</p>
    <a href="mailto:support@bsscholar.app?subject=Help%20Center%20Inquiry" class="btn outline blue full sidebar-help-btn">
        <span class="sidebar-help-btn-icon" aria-hidden="true">?</span>
        Go to Help Center
    </a>
</div>
