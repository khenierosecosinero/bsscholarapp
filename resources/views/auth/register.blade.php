@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Registration</h1>

            @if($errors->any())
                <div class="errors">{{ implode(' ', $errors->all()) }}</div>
            @endif

            <form method="POST" action="{{ route('register.post') }}" class="auth-form">
                @csrf
                <div class="auth-form-body">
                    <input class="form-input" type="text" name="full_name" placeholder="Full Name" value="{{ old('full_name') }}" required autocomplete="name" />
                    <input class="form-input" type="text" name="scholar_id" placeholder="Scholar ID" value="{{ old('scholar_id') }}" required />
                    @include('partials.scholar-club-select', [
                        'clubs' => $clubs,
                    ])
                    <p class="muted small" style="margin:-4px 0 12px;padding-left:4px">
                        Choose a Scholarship Club. The Province, Municipality/City, and School/University list come from that club.
                    </p>
                    <input class="form-input" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required autocomplete="email" />
                    <label class="location-cascade-label" for="course_year_level" style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin:0 0 6px;padding-left:4px">Course</label>
                    <input
                        class="form-input"
                        id="course_year_level"
                        type="text"
                        name="course_year_level"
                        placeholder="Bachelor of Science in ..."
                        value="{{ old('course_year_level') }}"
                        maxlength="255"
                        autocomplete="off"
                    />
                    <label class="location-cascade-label" for="year_level" style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin:0 0 6px;padding-left:4px">Year Level</label>
                    <select class="form-input form-select" id="year_level" name="year_level">
                        <option value="">Select year level</option>
                        @foreach(($yearLevels ?? []) as $yearLevel)
                            <option value="{{ $yearLevel }}" @selected(old('year_level') === $yearLevel)>{{ $yearLevel }}</option>
                        @endforeach
                    </select>
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

        @include('partials.auth-welcome', [
            'ctaRoute' => route('login'),
            'ctaLabel' => 'Login',
            'ctaVariant' => 'outline',
        ])
    </div>
</div>
@endsection
