@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Verification Code</h1>
            <p class="lead">Enter the 6-digit code sent to <strong>{{ $email }}</strong>. It must match the code in that email exactly.</p>

            @if(session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.verify.post') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <input
                        class="form-input verify-code-input"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        minlength="6"
                        placeholder="000000"
                        value="{{ old('code') }}"
                        required
                        autocomplete="one-time-code"
                    />
                </div>
                <div class="auth-form-footer">
                    <button class="btn login-btn" type="submit">Verify Code</button>
                </div>
            </form>

            <form method="POST" action="{{ route('password.resend') }}" class="resend-code-form">
                @csrf
                <button class="forgot resend-code-btn" type="submit">Resend verification code</button>
            </form>

            <p class="muted form-note"><a href="{{ route('password.request') }}" class="create">Use a different email</a></p>
        </div>

        @include('partials.auth-welcome', [
            'ctaRoute' => route('login'),
            'ctaLabel' => 'Login',
        ])
    </div>
</div>
@endsection
