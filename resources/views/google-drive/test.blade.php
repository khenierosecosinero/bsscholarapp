@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Google Drive test</h1>
            <p class="lead">Temporary page to prove OAuth and a Drive upload. This is not the scholar document workflow.</p>

            @if(session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="errors">{{ session('error') }}</div>
            @endif

            @if($errors->any())
                <div class="errors">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <p class="muted">
                @if($connected)
                    Google Drive is connected for this browser session.
                @else
                    Connect Google first, then upload a small PDF or image.
                    If Google shows <strong>Access blocked</strong>, add that Gmail as a test user under Google Cloud → Auth Platform → Audience. Laravel cannot override that.
                @endif
            </p>

            <p>
                <a class="btn" href="{{ route('google.redirect') }}">Connect Google Drive</a>
            </p>

            <form action="{{ route('google.drive.upload') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="auth-form">
                @csrf

                <div class="auth-form-body">
                    <label for="file">Select a file (10 MB or less)</label>
                    <input
                        class="form-input"
                        type="file"
                        id="file"
                        name="file"
                        required
                    >
                </div>

                <div class="auth-form-footer">
                    <button class="btn login-btn" type="submit">
                        Upload to Google Drive
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
