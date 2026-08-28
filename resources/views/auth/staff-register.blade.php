@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>City's Scholar Registration</h1>
            <p class="lead">Register as scholar staff to manage scholars in your assigned province and municipality/city.</p>

            @if($errors->any())
                <div class="errors">{{ implode(' ', $errors->all()) }}</div>
            @endif

            <form method="POST" action="{{ route('register.staff.post') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <input class="form-input" type="text" name="full_name" placeholder="Name of Scholar Staff" value="{{ old('full_name') }}" required autocomplete="name" />
                    <input class="form-input" type="text" name="scholar_id" placeholder="Scholar Staff Number" value="{{ old('scholar_id') }}" required />
                    @include('partials.location-select', [
                        'programGroups' => $programGroups,
                        'placeholder' => 'Select scholar program...',
                        'helpText' => 'City and province scholar programs are listed alphabetically in separate groups. Example: Surigao City — City Scholar Program, Surigao del Norte — Province Scholar Program.',
                    ])
                    <input class="form-input" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required autocomplete="email" />
                    <input class="form-input" type="password" name="password" placeholder="Password" required autocomplete="new-password" minlength="8" />
                    <input class="form-input" type="password" name="password_confirmation" placeholder="Confirm Password" required autocomplete="new-password" minlength="8" />
                </div>
                <div class="auth-form-footer">
                    <button class="btn register-btn" type="submit">Register Scholar Staff</button>
                    <p class="muted form-note">Already have an account? <a href="{{ route('login') }}" class="create">Login</a></p>
                </div>
            </form>
        </div>

        @include('partials.auth-welcome', ['ctaRoute' => route('login'), 'ctaLabel' => 'Login'])
    </div>
</div>
@endsection
