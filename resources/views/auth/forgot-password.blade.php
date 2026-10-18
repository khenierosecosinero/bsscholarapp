@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Forgot Password</h1>
            <p class="lead">Enter a registered email to find your account, then choose where to send the password reset link.</p>

            @include('partials.flash-messages')

            <form method="POST" action="{{ route('password.find') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <label class="location-cascade-label" for="lookup_email" style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin:0 0 6px;padding-left:4px">Registered email</label>
                    <input
                        class="form-input"
                        type="email"
                        id="lookup_email"
                        name="email"
                        placeholder="Primary or verified backup email"
                        value="{{ old('email', $lookupEmail ?? '') }}"
                        required
                        autocomplete="username"
                    />
                </div>
                <div class="auth-form-footer">
                    <button class="btn login-btn" type="submit">Find Account</button>
                </div>
            </form>

            @if(!empty($recoveryOptions))
                <div class="recovery-options" style="margin-top:18px;padding-top:16px;border-top:1px solid #e5e7eb">
                    <p class="lead" style="margin-bottom:12px">Account found. Select a recovery email:</p>
                    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                        @csrf
                        <div class="auth-form-body" style="gap:10px">
                            @foreach($recoveryOptions as $index => $option)
                                <label
                                    for="recovery_email_{{ $index }}"
                                    style="display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid #e5e7eb;border-radius:10px;cursor:pointer;text-align:left"
                                >
                                    <input
                                        type="radio"
                                        id="recovery_email_{{ $index }}"
                                        name="recovery_email"
                                        value="{{ $option['email'] }}"
                                        @checked(old('recovery_email', $index === 0 ? $option['email'] : '') === $option['email'])
                                        required
                                        style="margin-top:3px"
                                    >
                                    <span>
                                        <strong style="display:block;color:#111827;font-size:14px">{{ $option['email'] }}</strong>
                                        <small style="color:#6b7280">{{ $option['label'] }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="auth-form-footer">
                            <button class="btn login-btn" type="submit">Send Reset Link</button>
                            <p class="muted form-note"><a href="{{ route('password.request') }}" class="create">Search again</a></p>
                        </div>
                    </form>
                </div>
            @endif

            <p class="muted form-note" style="margin-top:14px"><a href="{{ route('login') }}" class="create">Back to Login</a></p>
        </div>

        @include('partials.auth-welcome', [
            'ctaRoute' => route('login'),
            'ctaLabel' => 'Login',
        ])
    </div>
</div>
@endsection
