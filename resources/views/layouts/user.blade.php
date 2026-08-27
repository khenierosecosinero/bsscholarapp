@extends('layouts.app')

@push('styles')
    @if(Auth::check() && Auth::user()->isPendingApproval())
        <link rel="stylesheet" href="{{ asset('css/pending-approval-modal.css') }}">
    @endif
@endpush

@section('content')
<div class="app-shell" id="app-shell">
    @include('partials.user-sidebar', ['active' => $active ?? 'dashboard'])
    <main class="main">
        <div class="inner">
            @include('partials.user-topbar', [
                'user' => $user ?? Auth::user(),
                'pageTitle' => $pageTitle ?? 'Dashboard',
                'pageSubtitle' => $pageSubtitle ?? '',
            ])
            @include('partials.flash-messages')
            @yield('page-content')
        </div>
    </main>
</div>
@include('partials.pending-approval-modal', ['showPendingApprovalModal' => $showPendingApprovalModal ?? false])
@endsection

@section('scripts')
    @vite(['resources/js/user-app.js'])
@endsection
