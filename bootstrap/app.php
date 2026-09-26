<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'scholar' => \App\Http\Middleware\EnsureScholar::class,
            'scholar.approved' => \App\Http\Middleware\EnsureScholarApproved::class,
            'scholar.staff' => \App\Http\Middleware\EnsureScholarStaff::class,
            'scholar.staff.approved' => \App\Http\Middleware\EnsureScholarStaffApproved::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = 'The uploaded file is too large. Use a file of 10 MB or less.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return redirect()
                ->back()
                ->with('error', $message);
        });
    })->create();
