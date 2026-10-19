@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Scholarship Club Registration</h1>

            @include('partials.flash-messages')

            <form method="POST" action="{{ route('register.staff.post') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <input class="form-input" type="text" name="full_name" placeholder="Name of Scholar Staff" value="{{ old('full_name') }}" required autocomplete="name" />
                    <label class="location-cascade-label" for="scholarship_club_name" style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin:0 0 6px;padding-left:4px">Scholarship Club Name</label>
                    <input class="form-input" type="text" id="scholarship_club_name" name="scholarship_club_name" placeholder="Scholarship Club Name" value="{{ old('scholarship_club_name') }}" required maxlength="255" pattern=".*\S.*" title="Enter a Scholarship Club Name that is not already registered." autocomplete="organization" />
                    @include('partials.staff-location-select', [
                        'locationTree' => $locationTree,
                        'requireCity' => true,
                        'addressMode' => true,
                    ])
                    <input class="form-input" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required autocomplete="email" />
                    <input class="form-input" type="text" name="cellphone_number" placeholder="Contact Number" value="{{ old('cellphone_number') }}" required maxlength="50" autocomplete="tel" />
                    <input class="form-input" type="password" name="password" placeholder="Password" required autocomplete="new-password" minlength="8" />
                    <input class="form-input" type="password" name="password_confirmation" placeholder="Confirm Password" required autocomplete="new-password" minlength="8" />
                </div>
                <div class="auth-form-footer">
                    <button class="btn register-btn" type="submit">Register Scholar Staff</button>
                    <p class="muted form-note">Already have an account? <a href="{{ route('login') }}" class="create">Login</a></p>
                </div>
            </form>
        </div>

        @include('partials.auth-welcome', [
            'ctaRoute' => route('login'),
            'ctaLabel' => 'Login',
            'ctaVariant' => 'outline',
        ])
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var form = document.querySelector('form.auth-form');
    var input = document.getElementById('scholarship_club_name');
    if (!form || !input) {
        return;
    }

    function normalizeClubName(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    input.addEventListener('blur', function () {
        input.value = normalizeClubName(input.value);
    });

    form.addEventListener('submit', function (event) {
        input.value = normalizeClubName(input.value);
        if (input.value === '') {
            event.preventDefault();
            input.setCustomValidity('Please enter a Scholarship Club Name.');
            input.reportValidity();
            return;
        }
        input.setCustomValidity('');
    });

    input.addEventListener('input', function () {
        input.setCustomValidity('');
    });
})();
</script>
@endsection
