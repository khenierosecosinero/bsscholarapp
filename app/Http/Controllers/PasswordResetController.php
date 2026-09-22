<?php

namespace App\Http\Controllers;

use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(
        private PasswordResetService $resets,
    ) {}

    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendCode(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $this->ensureSendIsNotRateLimited($request, $email);

        $user = $this->resets->eligibleUser($email);

        if (! $user) {
            RateLimiter::hit($this->sendThrottleKey($request, $email), 60);

            throw ValidationException::withMessages([
                'email' => 'This email is not registered as a Scholar or Scholar Staff account.',
            ]);
        }

        $this->resets->sendCode($user);

        RateLimiter::hit($this->sendThrottleKey($request, $email), 60);

        $request->session()->put(PasswordResetService::SESSION_EMAIL, $user->email);
        $request->session()->forget(PasswordResetService::SESSION_VERIFIED);

        return redirect()
            ->route('password.verify')
            ->with('success', 'A verification code was sent to your registered email address. Enter that exact code below.');
    }

    public function showVerifyForm(Request $request)
    {
        $email = $this->sessionEmail($request);

        if (! $email) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-reset-code', compact('email'));
    }

    public function verifyCode(Request $request)
    {
        $email = $this->requireSessionEmail($request);

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $this->ensureVerifyIsNotRateLimited($request, $email);

        try {
            $this->resets->verifyCode($email, (string) $request->input('code'));
        } catch (ValidationException $e) {
            RateLimiter::hit($this->verifyThrottleKey($request, $email), 300);
            throw $e;
        }

        RateLimiter::clear($this->verifyThrottleKey($request, $email));
        $request->session()->put(PasswordResetService::SESSION_VERIFIED, true);

        return redirect()
            ->route('password.reset')
            ->with('success', 'Verification successful. Create a new password for this account.');
    }

    public function resendCode(Request $request)
    {
        $email = $this->requireSessionEmail($request);
        $this->ensureSendIsNotRateLimited($request, $email);

        $user = $this->resets->eligibleUser($email);

        if (! $user) {
            $request->session()->forget([
                PasswordResetService::SESSION_EMAIL,
                PasswordResetService::SESSION_VERIFIED,
            ]);

            throw ValidationException::withMessages([
                'email' => 'This email is not registered as a Scholar or Scholar Staff account.',
            ]);
        }

        $this->resets->sendCode($user);
        $request->session()->forget(PasswordResetService::SESSION_VERIFIED);
        RateLimiter::hit($this->sendThrottleKey($request, $email), 60);

        return redirect()
            ->route('password.verify')
            ->with('success', 'A new verification code was sent to your registered email address.');
    }

    public function showResetForm(Request $request)
    {
        $email = $this->sessionEmail($request);

        if (! $email || ! $request->session()->get(PasswordResetService::SESSION_VERIFIED)) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password', compact('email'));
    }

    public function updatePassword(Request $request)
    {
        $email = $this->requireSessionEmail($request);

        if (! $request->session()->get(PasswordResetService::SESSION_VERIFIED)) {
            return redirect()->route('password.request');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $this->resets->resetPassword($email, $validated['password']);

        $request->session()->forget([
            PasswordResetService::SESSION_EMAIL,
            PasswordResetService::SESSION_VERIFIED,
        ]);
        $request->session()->regenerate();

        return redirect()
            ->route('login')
            ->with('success', 'Your password has been changed successfully. You can now log in with your new password.');
    }

    private function sessionEmail(Request $request): ?string
    {
        $email = $request->session()->get(PasswordResetService::SESSION_EMAIL);

        return is_string($email) && $email !== '' ? strtolower(trim($email)) : null;
    }

    private function requireSessionEmail(Request $request): string
    {
        $email = $this->sessionEmail($request);

        if (! $email) {
            throw ValidationException::withMessages([
                'email' => 'Start password recovery by entering the email you used during registration.',
            ])->redirectTo(route('password.request'));
        }

        return $email;
    }

    private function sendThrottleKey(Request $request, string $email): string
    {
        return 'password-reset-send|'.Str::lower($email).'|'.$request->ip();
    }

    private function verifyThrottleKey(Request $request, string $email): string
    {
        return 'password-reset-verify|'.Str::lower($email).'|'.$request->ip();
    }

    private function ensureSendIsNotRateLimited(Request $request, string $email): void
    {
        if (RateLimiter::tooManyAttempts($this->sendThrottleKey($request, $email), 1)) {
            $seconds = RateLimiter::availableIn($this->sendThrottleKey($request, $email));

            throw ValidationException::withMessages([
                'email' => "Please wait {$seconds} seconds before requesting another verification code.",
            ]);
        }
    }

    private function ensureVerifyIsNotRateLimited(Request $request, string $email): void
    {
        if (RateLimiter::tooManyAttempts($this->verifyThrottleKey($request, $email), 5)) {
            $seconds = RateLimiter::availableIn($this->verifyThrottleKey($request, $email));

            throw ValidationException::withMessages([
                'code' => "Too many verification attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }
}
