@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Forgot Password</h1>
            <p class="lead">Enter a registered email to find your account, then choose where to send the password reset link.</p>

            @include('partials.flash-messages')

            <form method="POST" action="{{ route('password.find') }}" class="auth-form" id="forgot-find-form" autocomplete="off">
                @csrf
                <div class="auth-form-body">
                    <label class="location-cascade-label" for="lookup_email" style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin:0 0 6px;padding-left:4px">Registered email</label>
                    <input
                        class="form-input"
                        type="email"
                        id="lookup_email"
                        name="email"
                        placeholder="Enter your registered email address"
                        value="{{ !empty($accountFound) ? ($lookupEmail ?? '') : '' }}"
                        @if(!empty($accountFound) && !empty($lookupEmail)) data-lookup-email="{{ $lookupEmail }}" @endif
                        required
                        autocomplete="off"
                    />
                    @error('email')
                        <p class="field-error" style="margin:6px 0 0;color:#b91c1c;font-size:13px">{{ $message }}</p>
                    @enderror
                </div>
                <div class="auth-form-footer">
                    <button class="btn login-btn" type="submit">Find Account</button>
                </div>
            </form>

            @if(!empty($accountFound) && !empty($recoveryOptions))
                <div class="recovery-options" id="recovery-options" style="margin-top:18px;padding-top:16px;border-top:1px solid #e5e7eb">
                    <p class="lead" style="margin-bottom:12px">Account found. Select a recovery email:</p>
                    <form method="POST" action="{{ route('password.email') }}" class="auth-form" autocomplete="off">
                        @csrf
                        <div class="auth-form-body" style="gap:10px">
                            @foreach($recoveryOptions as $index => $option)
                                @php
                                    $optionName = trim((string) ($option['account_name'] ?? ($accountName ?? '')));
                                    $optionEmail = trim((string) ($option['email'] ?? ''));
                                @endphp
                                @continue($optionEmail === '' || $optionName === '')
                                <label
                                    for="recovery_email_{{ $index }}"
                                    style="display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid #e5e7eb;border-radius:10px;cursor:pointer;text-align:left"
                                >
                                    <input
                                        type="radio"
                                        id="recovery_email_{{ $index }}"
                                        name="recovery_email"
                                        value="{{ $optionEmail }}"
                                        @checked($index === 0)
                                        required
                                        style="margin-top:3px"
                                    >
                                    <span style="display:block;min-width:0">
                                        <span style="display:block;color:#111827;font-size:14px;line-height:1.45;margin-bottom:2px">
                                            <strong>Name:</strong> {{ $optionName }}
                                        </span>
                                        <span style="display:block;color:#111827;font-size:14px;line-height:1.45;margin-bottom:2px">
                                            <strong>Email:</strong> {{ $optionEmail }}
                                        </span>
                                        <small style="color:#6b7280">{{ $option['label'] ?? 'Recovery email' }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('recovery_email')
                            <p class="field-error" style="margin:8px 0 0;color:#b91c1c;font-size:13px">{{ $message }}</p>
                        @enderror
                        <div class="auth-form-footer">
                            <button class="btn login-btn" type="submit">Send Reset Link</button>
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
@if(!empty($accountFound))
<script>
(function () {
    var input = document.getElementById('lookup_email');
    var recovery = document.getElementById('recovery-options');
    if (!input || !recovery) return;

    var foundEmail = (input.getAttribute('data-lookup-email') || '').trim().toLowerCase();
    var clearUrl = @json(route('password.request', ['again' => 1]));

    function clearRecoveryIfEmailChanged() {
        var current = (input.value || '').trim().toLowerCase();
        if (!foundEmail || current === foundEmail) return;

        recovery.remove();
        recovery = null;
        input.removeAttribute('data-lookup-email');

        if (window.fetch) {
            fetch(clearUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(function () {});
        }
    }

    input.addEventListener('input', clearRecoveryIfEmailChanged);
    input.addEventListener('change', clearRecoveryIfEmailChanged);
})();
</script>
@endif
@endsection
