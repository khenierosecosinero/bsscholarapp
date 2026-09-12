@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pending-approval-modal.css') }}">
    <style>
        .staff-pending-page {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
        }

        .staff-pending-card {
            width: min(560px, 100%);
            background: #fff;
            border-radius: 16px;
            padding: 32px 28px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
            text-align: center;
        }

        .staff-pending-card h1 {
            margin: 0 0 12px;
            font-size: 24px;
            color: #0f2744;
        }

        .staff-pending-card p {
            margin: 0 0 16px;
            color: #6b7280;
            line-height: 1.6;
        }

        .staff-pending-meta {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            margin: 20px 0;
            text-align: left;
        }

        .staff-pending-meta div + div {
            margin-top: 10px;
        }

        .staff-pending-meta strong {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 4px;
        }

        .staff-pending-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 24px;
        }
    </style>
@endpush

@section('content')
<div class="staff-pending-page">
    <div class="staff-pending-card">
        <h1>Scholar Staff Access Pending</h1>
        <p>
            Your scholar staff account has been created successfully and is currently waiting for approval from a system administrator.
            You cannot access the Scholar Staff dashboard or any staff features until your account is approved.
        </p>

        <div class="staff-pending-meta">
            <div>
                <strong>Account Name</strong>
                {{ $staff->full_name }}
            </div>
            <div>
                <strong>Assigned Program</strong>
                {{ $staff->scholarshipProgram?->programLabel() ?? 'Unassigned' }}
            </div>
            <div>
                <strong>Status</strong>
                Pending Admin Approval
            </div>
        </div>

        <p class="muted">Please check back after an administrator has reviewed your registration. You may log out and return at any time.</p>

        <div class="staff-pending-actions">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn filled">Logout</button>
            </form>
        </div>
    </div>
</div>

@include('partials.staff-pending-approval-modal', [
    'showPendingStaffApprovalModal' => $showPendingStaffApprovalModal ?? false,
])
@endsection
