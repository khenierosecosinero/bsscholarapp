<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScholarStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (! $user || ! $user->isScholarStaff()) {
            abort(403, 'This section is only available to scholar staff accounts.');
        }

        return $next($request);
    }
}
