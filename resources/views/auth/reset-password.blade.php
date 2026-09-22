@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>New Password</h1>
            <p class="lead">Create a new password for <strong>{{ $email }}</strong>. It must be at least 8 characters, and both fields must match.</p>

            @if(session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <div class="password-field">
                        <input class="form-input" type="password" name="password" placeholder="New Password" id="new-password" required autocomplete="new-password" minlength="8" />
                        <button type="button" class="toggle-password" data-target="new-password" aria-label="Show password">&#128065;</button>
                    </div>
                    <div class="password-field">
                        <input class="form-input" type="password" name="password_confirmation" placeholder="Confirm New Password" id="confirm-password" required autocomplete="new-password" minlength="8" />
                        <button type="button" class="toggle-password" data-target="confirm-password" aria-label="Show password">&#128065;</button>
                    </div>
                </div>
                <div class="auth-form-footer">
                    <button class="btn login-btn" type="submit">Save New Password</button>
                    <p class="muted form-note"><a href="{{ route('login') }}" class="create">Back to Login</a></p>
                </div>
            </form>
        </div>

        @include('partials.auth-welcome', [
            'ctaRoute' => route('login'),
            'ctaLabel' => 'Login',
        ])
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-password').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var field = document.getElementById(toggle.getAttribute('data-target'));
            if (!field) {
                return;
            }
            field.type = field.type === 'password' ? 'text' : 'password';
        });
    });
});
</script>
@endsection
