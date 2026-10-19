<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScholarApproved
{
    public const PENDING_FEATURES_WARNING = 'Your account is pending approval. Some features will remain unavailable until your account is approved.';

    public const PENDING_PROFILE_WARNING = 'Your account is pending approval. Profile and Settings access will be available after your account is approved.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isScholar() && ! $user->hasScholarPortalAccess()) {
            $warning = $request->routeIs('user.profile', 'user.profile.*')
                ? self::PENDING_PROFILE_WARNING
                : self::PENDING_FEATURES_WARNING;

            return redirect()
                ->route('user.dashboard')
                ->with('warning', $warning);
        }

        return $next($request);
    }
}
