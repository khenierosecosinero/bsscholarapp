@extends('layouts.user')

@section('page-content')

<article class="announcement-detail card">
    <a href="{{ route('user.announcements') }}" class="back-link">&#8592; Back to Announcements</a>

    <div class="announcement-detail-header">
        <div class="ann-icon blue lg">&#128226;</div>
        <div>
            <h2>{{ $announcement->title }}</h2>
            <p class="muted small">
                Posted {{ $announcement->published_at?->format('F d, Y \a\t g:i A') ?? $announcement->created_at->format('F d, Y \a\t g:i A') }}
            </p>
        </div>
    </div>

    <div class="announcement-detail-body">
        {!! nl2br(e($announcement->body)) !!}
    </div>

    <div class="announcement-detail-actions">
        @if(!$announcement->is_read)
            <form method="POST" action="{{ route('user.announcements.read', $announcement) }}">
                @csrf
                <button type="submit" class="btn outline small">Mark as Read</button>
            </form>
        @else
            <span class="badge confirmed">Read</span>
        @endif
        <a href="{{ route('user.dashboard') }}" class="btn outline small">Back to Dashboard</a>
    </div>
</article>

@endsection
