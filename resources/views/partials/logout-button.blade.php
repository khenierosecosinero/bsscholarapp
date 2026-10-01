@php
    $logoutButtonClass = $class ?? 'app-logout-btn';
    $logoutFormClass = $formClass ?? null;
@endphp
<form method="POST" action="{{ route('logout') }}"@if($logoutFormClass) class="{{ $logoutFormClass }}"@endif>
    @csrf
    <button type="submit" class="{{ $logoutButtonClass }}">
        <svg class="app-logout-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M10 17.25V14H4v-4h6V6.75L16 12l-6 5.25zM18 4h-7v2h7v12h-7v2h7a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/>
        </svg>
        <span>Logout</span>
    </button>
</form>
