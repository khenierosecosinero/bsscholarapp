<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pending Approval — Batang Surigaonon Scholar's App</title>
    @vite(['resources/css/styles.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/staff-admin.css') }}?v={{ @filemtime(public_path('css/staff-admin.css')) }}">
</head>
<body>
<div class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
    <div class="staff-card" style="max-width:520px;width:100%">
        <div class="staff-card-header"><h2>Pending Approval</h2></div>

        @include('partials.flash-messages')

        @include('partials.bssa-alert', [
            'type' => 'warning',
            'title' => 'Pending Approval',
            'message' => 'Your account is pending approval. Some features will remain unavailable until your account is approved.',
            'dismissible' => false,
            'autoDismiss' => false,
        ])

        <p style="margin:16px 0 8px"><strong>{{ $staff->full_name }}</strong></p>
        <p class="staff-muted" style="margin:0 0 8px">{{ $staff->email }}</p>
        <p style="margin:0 0 16px"><span class="staff-badge orange">Pending Approval</span></p>
        <p class="staff-muted">An administrator must approve your Scholar Staff registration before you can access the Scholar Staff dashboard, manage scholars, or use staff features. You do not need to register again after approval.</p>

        <form method="POST" action="{{ route('logout') }}" style="margin-top:20px">
            @csrf
            <button type="submit" class="staff-btn">Log out</button>
        </form>
    </div>
</div>
</body>
</html>
