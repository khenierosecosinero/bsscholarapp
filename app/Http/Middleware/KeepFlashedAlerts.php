<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeepFlashedAlerts
{
    /**
     * Background polls share the session and would otherwise consume
     * one-request flash messages such as profile save success.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->session()->keep(['success', 'error', 'status']);

        return $next($request);
    }
}
