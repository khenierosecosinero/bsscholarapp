@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Forgot Password</h1>
            <p class="lead">Enter the Gmail or email address you used when you registered as a Scholar or Scholar Staff.</p>

            @if(session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="errors">{{ session('error') }}</div>
            @endif

            @if($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <input class="form-input" type="email" name="email" placeholder="Registered email address" value="{{ old('email') }}" required autocomplete="username" />
                </div>
                <div class="auth-form-footer">
                    <button class="btn login-btn" type="submit">Send Verification Code</button>
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
