@extends('layouts.app')

@section('content')
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Google Drive test</h1>
            <p class="lead">Temporary page to prove OAuth and a Drive upload. This is not the scholar document workflow.</p>

            @include('partials.flash-messages')

            <p class="muted">
                @if($connected)
                    Google Drive is connected. In My Drive you should see
                    <strong>BSSA</strong> with
                    <strong>DOCUMENTS</strong> and
                    <strong>ATTENDANCE</strong>
                    @if(! empty($organizationReady) || ! empty($attendanceReady))
                        (organization folders were created or confirmed just now)
                    @endif
                    .
                    Scholar files go under province → municipality/city → scholar folders.
                @else
                    Connect Google first. After you connect, My Drive will have
                    <strong>BSSA / DOCUMENTS</strong> and
                    <strong>BSSA / ATTENDANCE</strong>,
                    each split by Surigao del Norte and Surigao del Sur.
                    Then you can upload a small PDF or image.
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
