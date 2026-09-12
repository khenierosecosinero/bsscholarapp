<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureScholarStaffApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isScholarStaff()) {
            abort(403, 'This section is only available to approved scholar staff accounts.');
        }

        if ($user->isStaffRejected()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Your scholar staff account has been rejected and can no longer access the system. Please contact the system administrator for assistance.');
        }

        if (! $user->hasStaffPortalAccess()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('warning', 'Your scholar staff account is pending administrator approval. You cannot access Scholar Staff features until an administrator approves your registration.');
        }

        return $next($request);
    }
}
