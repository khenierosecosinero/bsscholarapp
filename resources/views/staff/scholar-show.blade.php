@extends('layouts.staff')

@section('page-content')

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header"><h2>Scholar Profile</h2></div>
        <div class="staff-scholar-cell" style="margin-bottom:20px">
            <div class="staff-scholar-avatar" style="width:56px;height:56px;font-size:20px">{{ strtoupper(substr($scholar->full_name, 0, 1)) }}</div>
            <div>
                <strong style="font-size:18px">{{ $scholar->full_name }}</strong>
                <div class="staff-muted">{{ $scholar->scholar_id }}</div>
                <span class="staff-badge {{ $scholar->status === 'approved' ? 'green' : ($scholar->status === 'pending' ? 'orange' : 'red') }}">{{ ucfirst($scholar->status) }}</span>
            </div>
        </div>
        <div class="staff-list-item"><div><strong>Email</strong><div class="staff-muted">{{ $scholar->email }}</div></div></div>
        <div class="staff-list-item"><div><strong>Municipality / City</strong><div class="staff-muted">{{ $scholar->municipalityName() ?? '—' }}</div></div></div>
        <div class="staff-list-item"><div><strong>Province</strong><div class="staff-muted">{{ $scholar->provinceName() ?? '—' }}</div></div></div>
        <div class="staff-list-item"><div><strong>School</strong><div class="staff-muted">{{ $scholar->school_university ?? '—' }}</div></div></div>
        <div class="staff-list-item"><div><strong>Course / Year</strong><div class="staff-muted">{{ $scholar->course_year_level ?? '—' }}</div></div></div>

        @if($scholar->status === 'pending')
            <div style="display:flex;gap:10px;margin-top:20px">
                <form method="POST" action="{{ route('staff.scholars.approve', $scholar) }}">
                    @csrf
                    <button type="submit" class="staff-btn staff-btn-primary">Approve Account</button>
                </form>
                <form method="POST" action="{{ route('staff.scholars.reject', $scholar) }}" onsubmit="return confirm('Reject and permanently delete this account? This cannot be undone.');">
                    @csrf
                    <button type="submit" class="staff-btn" style="border-color:#ef4444;color:#ef4444">Reject &amp; Delete Account</button>
                </form>
            </div>
        @endif
    </div>

    <div class="staff-card">
        <div class="staff-card-header"><h2>Service Hours</h2></div>
        <div class="staff-stat-body">
            <div class="value">{{ $hourStats['approved'] }}</div>
            <div class="sub">Approved of {{ $hourStats['required'] }} required hours</div>
        </div>
        <div style="margin-top:16px">
            <p class="staff-muted">Pending: <strong>{{ $hourStats['pending'] }}</strong></p>
            <p class="staff-muted">Remaining: <strong>{{ $hourStats['remaining'] }}</strong></p>
        </div>
    </div>
</section>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Documents</h2></div>
    @if($documents->isEmpty())
        <p class="staff-muted">No documents on file.</p>
    @else
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Status</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td>
                                @if($document->documentType)
                                    <a href="{{ route('staff.documents.show', $document->documentType) }}">{{ $document->documentType->name }}</a>
                                @else
                                    Document
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusClass = match($document->status) {
                                        'approved' => 'green',
                                        'rejected' => 'red',
                                        'not_submitted' => 'gray',
                                        default => 'orange',
                                    };
                                @endphp
                                <span class="staff-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</span>
                            </td>
                            <td>{{ $document->uploaded_at?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                @if($document->file_path)
                                    <a href="{{ route('staff.documents.view', $document) }}" class="staff-btn staff-btn-sm" target="_blank" rel="noopener">Review</a>
                                    <a href="{{ route('staff.documents.download', $document) }}" class="staff-btn staff-btn-sm">Download</a>
                                @else
                                    <span class="staff-muted">No file</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Recent Activity</h2></div>
    @if($recentActivities->isEmpty())
        <p class="staff-muted">No recent activity recorded.</p>
    @else
        @foreach($recentActivities as $activity)
            <div class="staff-list-item">
                <div>
                    <strong>{{ $activity->description }}</strong>
                    <div class="staff-muted">{{ $activity->created_at->diffForHumans() }}</div>
                </div>
            </div>
        @endforeach
    @endif
</div>

<div style="margin-top:20px">
    <a href="{{ route('staff.scholars') }}" class="staff-card-link">&larr; Back to scholars list</a>
</div>

@endsection

@push('styles')
<style>.staff-muted{color:#6b7280;font-size:13px}</style>
@endpush
