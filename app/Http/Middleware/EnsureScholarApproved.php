<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScholarApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isScholar() && ! $user->hasScholarPortalAccess()) {
            return redirect()
                ->route('user.dashboard')
                ->with('warning', 'This section is unavailable until your account is approved by Scholar Staff.');
        }

        return $next($request);
    }
}
