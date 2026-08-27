@php
    $statusClass = $statusClass ?? 'not-joined';
    $statusLabel = $statusLabel ?? 'Not Joined';
    $small = $small ?? false;
@endphp
<span class="badge pill {{ $small ? 'small' : '' }} {{ $statusClass }}">{{ $statusLabel }}</span>
