@php
    $online = $scholar->isPresentNow();
@endphp
<span
    class="staff-presence {{ $online ? 'is-online' : 'is-offline' }}"
    data-scholar-presence="{{ $scholar->id }}"
    role="status"
>
    <span class="staff-presence-dot" aria-hidden="true"></span>
    <span class="staff-presence-label">{{ $scholar->presenceLabel() }}</span>
</span>
