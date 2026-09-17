@props(['user' => null])

@php
    $name = $user?->full_name ?? 'Scholar';
    $url = $user?->avatarUrl();
    $initial = $user?->initials() ?? 'S';
@endphp

@if($url)
    <button
        type="button"
        {{ $attributes->merge([
            'class' => 'user-avatar-wrap user-avatar-preview-trigger',
            'data-avatar-preview' => $url,
            'data-avatar-name' => $name,
            'title' => 'View profile photo of '.$name,
            'aria-label' => 'View profile photo of '.$name,
        ]) }}
    >
        <img src="{{ $url }}" alt="{{ $name }}" class="user-avatar-image">
    </button>
@else
    <div {{ $attributes->merge(['class' => 'user-avatar-wrap', 'title' => $name]) }}>
        <span class="user-avatar-fallback">{{ $initial }}</span>
    </div>
@endif
