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
                        <button type="button" id="togglePassword" aria-label="Show password">&#128065;</button>
                    </div>
                    <a class="forgot" href="#">Forgot Password?</a>
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
    toggle?.addEventListener('click', () => {
        pwd.type = pwd.type === 'password' ? 'text' : 'password';
    });
});
</script>
@endsection
