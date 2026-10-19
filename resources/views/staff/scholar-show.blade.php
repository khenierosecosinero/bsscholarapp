@extends('layouts.staff')

@section('page-content')

<section class="staff-grid-2">
    <div class="staff-card">
        <div class="staff-card-header"><h2>Scholar Profile</h2></div>
        <div class="staff-scholar-cell staff-scholar-profile">
            <x-user-avatar :user="$scholar" class="staff-scholar-avatar staff-scholar-avatar-lg" />
            <div class="staff-scholar-meta">
                <div class="staff-scholar-name-row">
                    <strong>{{ $scholar->full_name }}</strong>
                    @include('partials.staff-scholar-presence', ['scholar' => $scholar])
                </div>
                <small>{{ $scholar->scholar_id }}</small>
                <span class="staff-badge-row">
                    @include('partials.account-suspension-status', ['account' => $scholar, 'detailed' => true])
                </span>
            </div>
        </div>
        <div class="staff-list-item"><div><strong>Email</strong><div class="staff-muted">{{ $scholar->email }}</div></div></div>
        <div class="staff-list-item"><div><strong>Scholarship Club</strong><div class="staff-muted">{{ $scholar->scholarshipClubName() }}</div></div></div>
        <div class="staff-list-item"><div><strong>No./Street</strong><div class="staff-muted">{{ $scholar->streetName() ?: '—' }}</div></div></div>
        <div class="staff-list-item"><div><strong>Municipality / City</strong><div class="staff-muted">{{ $scholar->municipalityName() ?: ($scholar->scholarshipClubCity() ?? '—') }}</div></div></div>
        <div class="staff-list-item"><div><strong>Province</strong><div class="staff-muted">{{ $scholar->provinceName() ?: ($scholar->scholarshipClubProvince() ?? '—') }}</div></div></div>
        <div class="staff-list-item"><div><strong>Scholarship Club Address</strong><div class="staff-muted">{{ $scholar->scholarshipClubAddress() !== '' ? $scholar->scholarshipClubAddress() : '—' }}</div></div></div>
        <div class="staff-list-item"><div><strong>School</strong><div class="staff-muted">{{ $scholar->scholarshipClubSchool?->name ?: ($scholar->school_university ?: '—') }}</div></div></div>
        <div class="staff-list-item"><div><strong>Course</strong><div class="staff-muted">{{ $scholar->course_year_level ?? '—' }}</div></div></div>
        <div class="staff-list-item"><div><strong>Year Level</strong><div class="staff-muted">{{ $scholar->year_level ?? '—' }}</div></div></div>
        <div class="staff-list-item">
            <div>
                <strong>Start Academic Year</strong>
                <div class="staff-muted" style="margin-bottom:8px">
                    @if($scholar->program_start_year)
                        AY {{ $scholar->program_start_year }}–{{ $scholar->program_start_year + 1 }} (Year 1). Years 2–4 follow consecutively.
                    @else
                        Not set. Choose the academic year when this scholar originally began the scholarship.
                    @endif
                </div>
                <form method="POST" action="{{ route('staff.scholars.program-start', $scholar) }}" class="staff-form-grid" style="grid-template-columns:1fr auto;gap:8px;align-items:end">
                    @csrf
                    @method('PUT')
                    <div class="staff-form-group" style="margin:0">
                        <label for="program_start_year" class="staff-muted" style="display:block;margin-bottom:4px">Start Academic Year</label>
                        <select id="program_start_year" name="program_start_year" class="staff-select" required>
                            <option value="" disabled @selected(! $scholar->program_start_year)>Select start academic year</option>
                            @foreach($programStartYearOptions as $year => $label)
                                <option value="{{ $year }}" @selected((int) $scholar->program_start_year === (int) $year)>AY {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="staff-btn staff-btn-primary">Save</button>
                </form>
            </div>
        </div>

        @if($scholar->status === 'pending')
            <div class="staff-scholar-profile-actions" data-approval-actions>
                <form method="POST" action="{{ route('staff.scholars.approve', $scholar) }}" data-ajax-approval="approve" data-no-loading="true">
                    @csrf
                    <button type="submit" class="staff-btn staff-btn-primary">Approve Account</button>
                </form>
                <form
                    method="POST"
                    action="{{ route('staff.scholars.reject', $scholar) }}"
                    data-confirm="Reject this scholar application? The application record will be kept for review."
                    data-confirm-title="Reject this application?"
                    data-confirm-name="{{ $scholar->full_name }}"
                    data-confirm-note="The account will be marked Rejected and removed from active scholars. Staff can still open the application for review."
                    data-confirm-yes="Reject Application"
                    data-confirm-no="Cancel"
                    data-confirm-variant="danger"
                    data-ajax-approval="reject"
                    data-no-loading="true"
                >
                    @csrf
                    <button type="submit" class="staff-btn" style="border-color:#ef4444;color:#ef4444">Reject Application</button>
                </form>
            </div>
        @else
            <div class="staff-scholar-profile-actions">
                @if(in_array($scholar->status, ['approved', 'blocked', 'suspended'], true))
                    @include('partials.account-access-actions', [
                        'account' => $scholar,
                        'blockUrl' => route('staff.scholars.block', $scholar),
                        'suspendUrl' => route('staff.scholars.suspend', $scholar),
                        'reactivateUrl' => route('staff.scholars.reactivate', $scholar),
                        'showSuspensionReason' => true,
                    ])
                @endif
                <form
                    method="POST"
                    action="{{ route('staff.scholars.destroy', $scholar) }}"
                    data-confirm="Permanently delete this scholar account?"
                    data-confirm-title="Delete scholar account?"
                    data-confirm-name="{{ $scholar->full_name }}"
                    data-confirm-note="This cannot be undone. The scholar account will be permanently removed."
                    data-confirm-yes="Delete Account"
                    data-confirm-no="Cancel"
                    data-confirm-variant="danger"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="staff-btn" style="border-color:#ef4444;color:#ef4444">Delete Account</button>
                </form>
            </div>
        @endif
    </div>

    <div class="staff-card staff-hours-card">
        <div class="staff-card-header"><h2>Service Hours</h2></div>
        <p class="staff-hours-ay">Academic Year: <strong>{{ $semesterInfo['academic_year'] }}</strong></p>
        <p class="staff-hours-semester">{{ $semesterInfo['semester'] }}</p>
        <div class="staff-stat-body">
            <div class="staff-muted">Service Hours</div>
            <div class="value">{{ number_format($hourStats['approved'], 2) }}</div>
            <div class="sub">Approved of {{ number_format($hourStats['required'], 2) }} required hours</div>
        </div>
        <div class="staff-hours-meta">
            <p class="staff-muted">Pending: <strong>{{ number_format($hourStats['pending'], 2) }}</strong></p>
            <p class="staff-muted">Remaining: <strong>{{ number_format($hourStats['remaining'], 2) }}</strong></p>
        </div>
        <p class="staff-muted staff-hours-note">{{ number_format($hourStats['required'], 2) }} hours required this semester. Pending hours are not counted as completed. Extra approved hours above {{ number_format($hourStats['required'], 2) }} are credited to the next semester.</p>
    </div>
</section>

@include('partials.program-hour-progress')

<div class="staff-card staff-hours-records-card">
    <div class="staff-card-header"><h2>Service Hour Records</h2></div>
    @if($attendances->isEmpty())
        <p class="staff-muted">No service hour records for this academic year.</p>
    @else
        <div class="staff-table-wrap">
            <table class="staff-table staff-stack-table staff-manage-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Check In / Out</th>
                        <th>Hours</th>
                        <th class="staff-col-status">Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendances as $attendance)
                        <tr>
                            <td data-label="Event">{{ $attendance->event?->title ?? '—' }}</td>
                            <td data-label="Check In / Out">
                                {{ $attendance->check_in?->format('M j, g:i A') ?? '—' }}
                                <div class="staff-muted">{{ $attendance->check_out?->format('g:i A') ?? '—' }}</div>
                            </td>
                            <td data-label="Hours">{{ $attendance->hoursLabel() }}</td>
                            <td data-label="Status" class="staff-col-status">
                                @php
                                    $statusClass = match($attendance->status) {
                                        'approved' => 'green',
                                        'rejected', 'failed_to_check_in' => 'red',
                                        default => 'orange',
                                    };
                                @endphp
                                <span class="staff-badge {{ $statusClass }}">{{ $attendance->statusLabel() }}</span>
                            </td>
                            <td data-label="Notes"><div class="staff-stack-value staff-muted">{{ $attendance->reviewNote() }}</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="staff-card" style="margin-top:20px">
    <div class="staff-card-header"><h2>Documents</h2></div>
    @if($documents->isEmpty())
        <p class="staff-muted">No documents on file.</p>
    @else
        <div class="staff-table-wrap">
            <table class="staff-table staff-stack-table staff-manage-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th class="staff-col-status">Status</th>
                        <th>Uploaded</th>
                        <th class="staff-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td data-label="Document">
                                @if($document->documentType)
                                    <a href="{{ route('staff.documents.show', $document->documentType) }}">{{ $document->documentType->name }}</a>
                                @else
                                    Document
                                @endif
                            </td>
                            <td data-label="Status" class="staff-col-status">
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
                            <td data-label="Uploaded">{{ $document->uploaded_at?->format('M d, Y') ?? '—' }}</td>
                            <td data-label="Actions" class="staff-col-actions">
                                @if($document->file_path)
                                    <div class="staff-action-group">
                                        <a href="{{ route('staff.documents.view', $document) }}" class="staff-btn staff-btn-sm" target="_blank" rel="noopener">Review</a>
                                        <a href="{{ route('staff.documents.download', $document) }}" class="staff-btn staff-btn-sm">Download</a>
                                    </div>
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

<div hidden id="staff-scholar-presence-root" data-presence-url="{{ route('staff.scholars.presence') }}"></div>

@endsection

@push('styles')
<style>.staff-muted{color:#6b7280;font-size:13px}</style>
@endpush
