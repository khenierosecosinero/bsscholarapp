@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Verify Your Email</h1>
            <p class="lead">
                We sent a verification link to
                <strong>{{ $email }}</strong>.
                Open that email and click the link to confirm your address.
                The link expires in {{ (int) config('auth.verification.expire', 60) }} minutes.
            </p>

            @include('partials.flash-messages')

            @if(empty($mailReady))
                @include('partials.bssa-alert', [
                    'type' => 'error',
                    'title' => 'Email delivery unavailable',
                    'message' => 'Email delivery is not ready on this server. Ask an administrator to configure Gmail SMTP.',
                    'dismissible' => false,
                    'autoDismiss' => false,
                ])
            @endif

            <form method="POST" action="{{ route('verification.send') }}" class="auth-form">
                @csrf
                <div class="auth-form-footer">
                    <button class="btn login-btn" type="submit">Resend verification email</button>
                    <p class="muted form-note">
                        <a href="{{ route('user.dashboard') }}" class="create">Continue to Dashboard</a>
                        ·
                        <a href="{{ route('logout') }}" class="create"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Log out</a>
                    </p>
                </div>
            </form>

            <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden" hidden>
                @csrf
            </form>
        </div>

        @include('partials.auth-welcome', [
            'ctaRoute' => route('login'),
            'ctaLabel' => 'Login',
        ])
    </div>
</div>
@endsection
