<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MissingResourceRedirect
{
    public static function message(): string
    {
        return 'The page or record you requested is no longer available.';
    }

    public static function homeRouteFor(?User $user, ?Request $request = null): string
    {
        if ($user?->isScholarStaff() && $user->hasStaffPortalAccess()) {
            if ($request && $request->is('staff/scholars', 'staff/scholars/*')) {
                return 'staff.scholars';
            }

            return 'staff.dashboard';
        }

        if ($user?->isAdmin()) {
            if ($request && $request->is('admin/scholars', 'admin/scholars/*')) {
                return 'admin.scholars';
            }

            if ($request && $request->is('admin/staff', 'admin/staff/*')) {
                return 'admin.staff';
            }

            return 'admin.dashboard';
        }

        if ($user?->isScholar() && $user->hasScholarPortalAccess()) {
            return 'user.dashboard';
        }

        return 'login';
    }

    public static function toSafePage(Request $request, ?string $message = null): RedirectResponse
    {
        $user = $request->user();
        $route = self::homeRouteFor($user, $request);
        $flash = $message ?: self::message();

        if ($route === 'staff.scholars') {
            $flash = 'That scholar record is no longer available. You have been returned to Scholar Management.';
        }

        if ($route === 'login') {
            return redirect()
                ->route('login')
                ->with('warning', $flash);
        }

        return redirect()
            ->route($route)
            ->with('warning', $flash);
    }
}
