@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Registration</h1>
            <p class="lead">Create your scholar account to get started. Select your province and designated area to be connected to the correct City or Province Scholarship Program.</p>

            @if($errors->any())
                <div class="errors">{{ implode(' ', $errors->all()) }}</div>
            @endif

            <form method="POST" action="{{ route('register.post') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <input class="form-input" type="text" name="full_name" placeholder="Full Name" value="{{ old('full_name') }}" required autocomplete="name" />
                    <input class="form-input" type="text" name="scholar_id" placeholder="Scholar ID" value="{{ old('scholar_id') }}" required />
                    @include('partials.staff-location-select', [
                        'locationTree' => $locationTree,
                        'requireCity' => false,
                    ])
                    <p class="muted small" style="margin:-4px 0 12px;padding-left:4px">
                        Choose your province, then select your municipality/city for a City Scholarship Program, or the province-wide option for a Province Scholarship Program.
                    </p>
                    <input class="form-input" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required autocomplete="email" />
                    <input class="form-input" type="text" name="school_university" placeholder="School/University" value="{{ old('school_university') }}" />
                    <input class="form-input" type="text" name="course_year_level" placeholder="Course and Year Level" value="{{ old('course_year_level') }}" />
                    <input class="form-input" type="text" name="cellphone_number" placeholder="Cellphone Number" value="{{ old('cellphone_number') }}" autocomplete="tel" />
                    <input class="form-input" type="password" name="password" placeholder="Password" required autocomplete="new-password" minlength="8" />
                    <input class="form-input" type="password" name="password_confirmation" placeholder="Confirm Password" required autocomplete="new-password" minlength="8" />
                </div>
                <div class="auth-form-footer">
                    <button class="btn register-btn" type="submit">Register</button>
                    <p class="muted form-note">Already have an account? <a href="{{ route('login') }}" class="create">Login</a></p>
                </div>
            </form>
        </div>

        @include('partials.auth-welcome', ['ctaRoute' => route('login'), 'ctaLabel' => 'Login'])
    </div>
</div>
@endsection
