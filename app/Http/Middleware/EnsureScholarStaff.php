<?php

namespace App\Http\Middleware;

use App\Support\AccountAccessGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureScholarStaff
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

        if (! $user || ! $user->isScholarStaff()) {
            abort(403, 'This section is only available to scholar staff accounts.');
        }

        if ($user->isStaffRejected()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Your scholar staff account has been rejected and can no longer access the system. Please contact the system administrator for assistance.');
        }

        if ($user->isStaffInactive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Your scholar staff account has been deactivated and cannot access the Scholar Staff section. Please contact the system administrator for assistance.');
        }

        if ($user->isStaffPendingApproval()) {
            if ($request->routeIs('staff.pending-approval', 'staff.dismiss-pending-modal')) {
                return $next($request);
            }

            return redirect()
                ->route('staff.pending-approval')
                ->with('warning', 'Your account is pending approval. Some features will remain unavailable until your account is approved.');
        }

        return $next($request);
    }
}
