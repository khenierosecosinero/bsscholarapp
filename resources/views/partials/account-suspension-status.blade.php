@php
    $account = $account ?? null;
    $suspension = $account?->openSuspension();
    $detailed = $detailed ?? false;
@endphp

@if($account)
    <span class="staff-badge {{ $account->staffStatusBadgeClass() }}">{{ $account->accountStatusLabel() }}</span>
    @if($account->isSuspended() && $suspension)
        <div class="staff-suspension-details">
            <span>Started {{ $suspension->starts_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
            <span>Ends {{ $suspension->ends_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
            @if($detailed)
                <span>Issued by {{ $suspension->issuer?->full_name ?: 'Scholar Staff' }}</span>
                @if(filled($suspension->reason))
                    <span>Reason: {{ $suspension->reason }}</span>
                @endif
            @endif
        </div>
    @endif
@endif
