<?php

namespace App\Http\Middleware;

use App\Support\AccountAccessGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureScholar
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($redirect = AccountAccessGuard::redirectIfRestricted($request)) {
            return $redirect;
        }

        $user = $request->user();

        if ($user && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user && $user->isScholarStaff()) {
            if ($user->isStaffRejected()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('error', 'Your scholar staff account has been rejected and can no longer access the system. Please contact the system administrator for assistance.');
            }

            if ($user->isStaffPendingApproval()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('warning', 'Your scholar staff account is pending administrator approval. You cannot log in until your registration has been approved.');
            }

            return redirect()->route('staff.dashboard');
        }

        if ($user && $user->isRejected()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Your account has been rejected and can no longer access the system. Please contact Scholar Staff for assistance.');
        }

        if ($user && $user->isPendingApproval()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('warning', 'Your account is currently pending approval from the authorized scholar staff. Please wait for your account to be reviewed and approved before logging in.');
        }

        return $next($request);
    }
}
