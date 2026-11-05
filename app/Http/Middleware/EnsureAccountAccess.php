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
        // Guest pages do not change suspension state. Login still releases
        // an expired suspension before the password is checked.
        if ($request->user() || ($request->isMethod('POST') && $request->is('login'))) {
            $this->accountAccess->releaseExpiredSuspensions();
        }

        if ($redirect = AccountAccessGuard::redirectIfRestricted($request)) {
            return $redirect;
        }

        return $next($request);
    }
}
