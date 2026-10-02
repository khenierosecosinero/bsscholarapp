@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Login</h1>
            <p class="lead">Welcome back. You can log in at any time — accounts stay active even after a long period of inactivity.</p>

            @if(session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="errors">{{ session('error') }}</div>
            @endif

            @if(session('warning'))
                <div class="errors" style="background:#fff7ed;border-color:#fed7aa;color:#9a3412">{{ session('warning') }}</div>
            @endif

            @if($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <input class="form-input" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required autocomplete="username" />
                    <div class="password-field">
                        <input class="form-input" type="password" name="password" placeholder="Password" id="password" required autocomplete="current-password" />
                        <button type="button" id="togglePassword" class="password-toggle" aria-label="Show password" aria-pressed="false" aria-controls="password">
                            <svg class="password-toggle-icon password-toggle-icon-hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                            </svg>
                            <svg class="password-toggle-icon password-toggle-icon-visible" hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <a class="forgot" href="{{ route('password.request') }}">Forgot Password?</a>
                </div>
                <div class="auth-form-footer">
                    <button class="btn login-btn" type="submit">Login</button>
                    <p class="muted form-note">New scholar? <a href="{{ route('register') }}" class="create">Create an Account</a></p>
                </div>
            </form>
        </div>

        @include('partials.auth-welcome', [
            'ctaRoute' => route('register'),
            'ctaLabel' => 'Create an Account',
            'ctaVariant' => 'outline',
            'staffRegisterRoute' => route('register.staff'),
        ])
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('togglePassword');
    const pwd = document.getElementById('password');
    if (!toggle || !pwd) {
        return;
    }

    const closedIcon = toggle.querySelector('.password-toggle-icon-hidden');
    const openIcon = toggle.querySelector('.password-toggle-icon-visible');

    function setPasswordHidden(hidden) {
        pwd.type = hidden ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', hidden ? 'false' : 'true');
        toggle.setAttribute('aria-label', hidden ? 'Show password' : 'Hide password');
        closedIcon?.toggleAttribute('hidden', !hidden);
        openIcon?.toggleAttribute('hidden', hidden);
    }

    setPasswordHidden(true);

    toggle.addEventListener('click', function () {
        setPasswordHidden(pwd.type !== 'password');
    });
});
</script>
@endsection
