@php
    $type = $type ?? 'info';
    $title = $title ?? null;
    $message = $message ?? null;
    $messages = $messages ?? [];
    $dismissible = $dismissible ?? true;
    $autoDismiss = $autoDismiss ?? false;
    $autoDismissMs = is_numeric($autoDismiss) ? (int) $autoDismiss : ($autoDismiss ? 5000 : 0);
    $class = trim('bssa-alert bssa-alert--'.$type.' flash-alert '.($class ?? ''));
@endphp
<div
    class="{{ $class }}"
    role="{{ $type === 'error' || $type === 'warning' ? 'alert' : 'status' }}"
    @if($autoDismissMs > 0) data-bssa-auto-dismiss="{{ $autoDismissMs }}" @endif
>
    <div class="bssa-alert__icon" aria-hidden="true">
        @if($type === 'success')
            <svg class="bssa-alert__icon-svg" viewBox="0 0 24 24" fill="none" focusable="false">
                <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.15"/>
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
                <path d="M7.5 12.5l3 3 6-6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @elseif($type === 'error')
            <svg class="bssa-alert__icon-svg" viewBox="0 0 24 24" fill="none" focusable="false">
                <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.12"/>
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
                <path d="M12 7.5v5.5M12 16.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        @elseif($type === 'warning')
            <svg class="bssa-alert__icon-svg" viewBox="0 0 24 24" fill="none" focusable="false">
                <path d="M12 3.8L21 20H3L12 3.8Z" fill="currentColor" opacity="0.12" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                <path d="M12 10v4.5M12 17.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        @else
            <svg class="bssa-alert__icon-svg" viewBox="0 0 24 24" fill="none" focusable="false">
                <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.12"/>
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
                <path d="M12 10.5V17M12 7.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        @endif
    </div>
    <div class="bssa-alert__body">
        @if($title)
            <p class="bssa-alert__title">{{ $title }}</p>
        @endif
        @if($message)
            <p class="bssa-alert__message">{{ $message }}</p>
        @endif
        @if(!empty($messages))
            <ul class="bssa-alert__list">
                @foreach($messages as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    @if($dismissible)
        <button type="button" class="bssa-alert__close" data-bssa-alert-close aria-label="Dismiss notification">&times;</button>
    @endif
</div>
