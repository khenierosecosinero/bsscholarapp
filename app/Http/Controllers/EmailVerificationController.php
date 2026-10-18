<?php

namespace App\Http\Controllers;

use App\Services\MailConfigService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        $user = $request->user();

        if ($user && $user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email', [
            'email' => $user?->email,
            'mailReady' => app(MailConfigService::class)->isReady(),
        ]);
    }

    public function verify(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Your email address is already verified.');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Your email address has been verified successfully.');
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Your email address is already verified.');
        }

        $key = 'email-verification-resend|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->with('error', "Please wait {$seconds} seconds before requesting another verification email.");
        }

        $mailConfig = app(MailConfigService::class);
        $mailConfig->syncRuntimeCredentials();

        if (! $mailConfig->isReady()) {
            return back()->with(
                'error',
                'Email delivery is not configured. Ask an administrator to set Gmail SMTP and run php artisan config:clear.'
            );
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::warning('Email verification resend failed.', [
                'user_id' => $user->id,
                'exception' => $e::class,
            ]);

            return back()->with('error', 'The verification email could not be sent through Gmail SMTP. Please try again later.');
        }

        RateLimiter::hit($key, 60);

        return back()->with('success', 'A new verification link was sent to your registered email address.');
    }
}
