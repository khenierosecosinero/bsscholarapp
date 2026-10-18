<?php

namespace App\Http\Middleware;

use App\Services\AccountAccessService;
use App\Support\AccountAccessGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountAccess
{
    public function __construct(private AccountAccessService $accountAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->accountAccess->releaseExpiredSuspensions();

        if ($redirect = AccountAccessGuard::redirectIfRestricted($request)) {
            return $redirect;
        }

        return $next($request);
    }
}
