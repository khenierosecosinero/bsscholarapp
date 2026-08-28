@php
    $slug = $slug ?? 'default';
    $size = $size ?? 'md';

    $icons = [
        'birth-certificate' => [
            'color' => 'green',
            'label' => 'Birth Certificate',
            'svg' => '<path d="M8 4h8a2 2 0 0 1 2 2v14l-6-3-6 3V6a2 2 0 0 1 2-2z"/><circle cx="12" cy="10" r="2.5"/><path d="M9.5 14.5c.8-1.2 2.2-1.2 3 0"/>',
        ],
        'student-id' => [
            'color' => 'red',
            'label' => 'Student ID',
            'svg' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.6-1.7 2-2.5 3-2.5s2.4.8 3 2.5"/><path d="M15 9h4M15 12h4M15 15h2"/>',
        ],
        'latest-grades' => [
            'color' => 'orange',
            'label' => 'Latest Grades',
            'svg' => '<path d="M4 19V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v14"/><path d="M8 15l2.5-3 2 2.5L14 12l4 5"/><path d="M8 8h2M8 11h3"/>',
        ],
        'certificate-of-registration' => [
            'color' => 'blue',
            'label' => 'Certificate of Registration',
            'svg' => '<path d="M9 3h6l1 2h4a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4l1-2z"/><path d="M9 12l2 2 4-4"/>',
        ],
        'good-moral-certificate' => [
            'color' => 'red',
            'label' => 'Good Moral Certificate',
            'svg' => '<path d="M12 3l2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5L4.8 8.2l5-.7L12 3z"/><path d="M8 19h8"/><path d="M10 21h4"/>',
        ],
        'event' => [
            'color' => 'blue',
            'label' => 'Events',
            'svg' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M8 3v4M16 3v4"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
        ],
        'default' => [
            'color' => 'blue',
            'label' => 'Document',
            'svg' => '<path d="M8 4h6l4 4v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M14 4v4h4"/>',
        ],
    ];

    $icon = $icons[$slug] ?? $icons['default'];
    $sizeClass = match ($size) {
        'sm' => 'doc-icon-wrap sm',
        'lg' => 'doc-icon-wrap lg',
        default => 'doc-icon-wrap',
    };
@endphp

<span class="{{ $sizeClass }} {{ $icon['color'] }}" role="img" aria-label="{{ $icon['label'] }} icon">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        {!! $icon['svg'] !!}
    </svg>
</span>
