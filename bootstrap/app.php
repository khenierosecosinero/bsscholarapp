<?php

use App\Support\MissingResourceRedirect;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountAccess::class,
        ]);

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

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            // Keep intentional removals (e.g. email verification) as real 404s.
            if ($request->is('email/*', 'up', 'favicon.ico')) {
                return null;
            }

            $previous = $e->getPrevious();
            $isMissingModel = $previous instanceof ModelNotFoundException
                || str_contains(strtolower((string) $e->getMessage()), 'no query results');

            // Soft-land only when a bound model is missing (e.g. deleted scholar ID).
            // Leave unmatched routes and controller abort(404) gates unchanged.
            if ($isMissingModel) {
                return MissingResourceRedirect::toSafePage($request);
            }

            return null;
        });
    })->create();
