@php
    $account = $account ?? null;
    $blockUrl = $blockUrl ?? null;
    $suspendUrl = $suspendUrl ?? null;
    $reactivateUrl = $reactivateUrl ?? null;
    $showSuspensionReason = (bool) ($showSuspensionReason ?? false);
    $showBlock = $account && $blockUrl && ($account->status === \App\Models\User::STATUS_APPROVED || $account->isSuspended());
    $showSuspend = $account && $suspendUrl && ($account->status === \App\Models\User::STATUS_APPROVED || $account->isBlocked());
    $showReactivate = $account && $reactivateUrl && $account->isAccessRestricted();
    $scholarSuspension = $account && $account->isScholar();
    $suspendUntil = now()->addDays(\App\Services\AccountAccessService::SUSPENSION_DAYS)->timezone(config('app.timezone'))->format('M j, Y g:i A');
    $suspendConfirm = $scholarSuspension
        ? $account->full_name.' will be suspended for 7 days, until '.$suspendUntil.'. The scholar cannot log in during that time. The account reactivates automatically when the suspension ends.'
        : $account->full_name.' will not be able to log in or use the system until the account is reactivated.';
@endphp

@if($account && ($showBlock || $showSuspend || $showReactivate))
    <div class="staff-action-group">
        @if($showBlock)
            <form method="POST" action="{{ $blockUrl }}" data-confirm="{{ $account->full_name }} will not be able to log in or use the system until the account is reactivated." data-confirm-title="Block this account?" data-confirm-name="{{ $account->full_name }}" data-confirm-yes="Block" data-confirm-no="Cancel" data-confirm-variant="danger">
                @csrf
                <button type="submit" class="staff-btn staff-btn-sm staff-btn-danger">Block</button>
            </form>
        @endif
        @if($showSuspend)
            <form method="POST" action="{{ $suspendUrl }}" @class(['staff-suspension-form' => $showSuspensionReason]) data-confirm="{{ $suspendConfirm }}" data-confirm-title="{{ $scholarSuspension ? 'Suspend this account for 7 days?' : 'Suspend this account?' }}" data-confirm-name="{{ $account->full_name }}" data-confirm-yes="Suspend" data-confirm-no="Cancel" data-confirm-variant="danger">
                @csrf
                @if($showSuspensionReason && $scholarSuspension)
                    <label class="staff-suspension-reason">
                        <span>Suspension reason (optional)</span>
                        <textarea name="reason" maxlength="500" rows="2" placeholder="Why is this account being suspended?"></textarea>
                    </label>
                @endif
                <button type="submit" class="staff-btn staff-btn-sm staff-btn-warning">Suspend</button>
            </form>
        @endif
        @if($showReactivate)
            <form method="POST" action="{{ $reactivateUrl }}" data-confirm="{{ $account->full_name }} will be able to log in and use the system again." data-confirm-title="Reactivate this account?" data-confirm-name="{{ $account->full_name }}" data-confirm-yes="Reactivate" data-confirm-no="Cancel">
                @csrf
                <button type="submit" class="staff-btn staff-btn-sm staff-btn-success">Reactivate</button>
            </form>
        @endif
    </div>
@endif
