@extends('layouts.staff')

@section('page-content')

<section class="staff-stat-grid">
    <div class="staff-stat-card"><div class="staff-stat-icon blue">📄</div><div class="staff-stat-body"><h3>Required Documents</h3><div class="value">{{ $stats['types'] }}</div><div class="sub">Posted types</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon orange">⏱</div><div class="staff-stat-body"><h3>Pending Review</h3><div class="value">{{ $stats['pending'] }}</div><div class="sub">Awaiting action</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon green">✓</div><div class="staff-stat-body"><h3>Approved</h3><div class="value">{{ $stats['approved'] }}</div><div class="sub">Verified submissions</div></div></div>
    <div class="staff-stat-card"><div class="staff-stat-icon red">✕</div><div class="staff-stat-body"><h3>Rejected</h3><div class="value">{{ $stats['rejected'] }}</div><div class="sub">Needs resubmission</div></div></div>
</section>

<form method="GET" class="staff-filter-bar">
    <div class="staff-search">
        <span>🔍</span>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search required documents...">
    </div>
    <button type="submit" class="staff-btn">Search</button>
    <a href="{{ route('staff.documents.create') }}" class="staff-btn staff-btn-primary">+ Add Required Document</a>
</form>

@if($types->isEmpty())
    <div class="staff-card">
        <p class="staff-muted" style="margin:0 0 12px">No required documents posted yet. Only Scholar Staff can add the documents scholars must submit.</p>
        <a href="{{ route('staff.documents.create') }}" class="staff-btn staff-btn-primary">+ Add Required Document</a>
    </div>
@else
    <div class="staff-doc-type-grid">
        @foreach($types as $type)
            <article class="staff-doc-type-card">
                <a href="{{ route('staff.documents.show', $type) }}" class="staff-doc-type-main">
                    @include('partials.document-icon', ['slug' => $type->slug, 'size' => 'lg'])
                    <div class="staff-doc-type-body">
                        <div class="staff-doc-type-title-row">
                            <h3>{{ $type->name }}</h3>
                            <span class="staff-badge {{ $type->required ? 'blue' : 'gray' }}">{{ $type->required ? 'Required' : 'Optional' }}</span>
                        </div>
                        <p>{{ $type->description ?: 'No description provided.' }}</p>
                        <div class="staff-doc-type-counts">
                            <span>{{ $type->submitted_count }} submitted</span>
                            <span class="pending">{{ $type->pending_count }} pending</span>
                            <span class="approved">{{ $type->approved_count }} approved</span>
                            <span class="rejected">{{ $type->rejected_count }} rejected</span>
                        </div>
                    </div>
                </a>
                <div class="staff-doc-type-actions">
                    <a href="{{ route('staff.documents.show', $type) }}" class="staff-btn staff-btn-sm">View submissions</a>
                    <a href="{{ route('staff.documents.edit', $type) }}" class="staff-btn staff-btn-sm">Edit</a>
                    <form method="POST" action="{{ route('staff.documents.destroy', $type) }}" onsubmit="return confirm('Remove {{ addslashes($type->name) }}? Scholar files for this type will also be deleted.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Delete</button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>
@endif

@endsection
