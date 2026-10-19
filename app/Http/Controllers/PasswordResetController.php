<?php

namespace App\Http\Controllers;

use App\Models\User;
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

    public function showRequestForm(Request $request)
    {
        if ($request->boolean('again') || $request->boolean('fresh')) {
            $this->forgetFindSession($request);

            return redirect()->route('password.request');
        }

        // Cold opens (bookmark, refresh, Login → Forgot Password) must never restore a prior find.
        // Recovery HTML is returned only when this request carries the one-time reveal flash
        // set by a successful Find Account (or a Send Reset Link error that re-flashes it).
        $reveal = $request->session()->get(PasswordResetService::FLASH_REVEAL) === true;

        if (! $reveal) {
            $this->forgetFindSession($request);
            $this->forgetLookupOldInput($request);

            return view('auth.forgot-password', [
                'accountFound' => false,
                'recoveryOptions' => [],
                'accountName' => '',
                'lookupEmail' => '',
            ]);
        }

        $options = $request->session()->get(PasswordResetService::SESSION_RECOVERY_OPTIONS);
        $accountName = $request->session()->get(PasswordResetService::SESSION_ACCOUNT_NAME);
        $lookupEmail = $request->session()->get(PasswordResetService::SESSION_LOOKUP_EMAIL);
        $accountId = (int) $request->session()->get(PasswordResetService::SESSION_ACCOUNT_ID);

        if ((! is_string($accountName) || trim($accountName) === '') && $accountId > 0) {
            $accountName = trim((string) (User::query()->whereKey($accountId)->value('full_name') ?? ''));
            if ($accountName !== '') {
                $request->session()->put(PasswordResetService::SESSION_ACCOUNT_NAME, $accountName);
            }
        }

        $accountName = is_string($accountName) ? trim($accountName) : '';
        $lookupEmail = is_string($lookupEmail) ? strtolower(trim($lookupEmail)) : '';
        $recoveryOptions = is_array($options) ? array_values(array_filter($options, function ($option) use ($accountName) {
            $email = trim((string) ($option['email'] ?? ''));
            $name = trim((string) ($option['account_name'] ?? $accountName));

            return $email !== '' && $name !== '';
        })) : [];

        $accountFound = $accountId > 0 && $accountName !== '' && $lookupEmail !== '' && $recoveryOptions !== [];

        if (! $accountFound) {
            $this->forgetFindSession($request);
            $this->forgetLookupOldInput($request);

            return view('auth.forgot-password', [
                'accountFound' => false,
                'recoveryOptions' => [],
                'accountName' => '',
                'lookupEmail' => '',
            ]);
        }

        return view('auth.forgot-password', [
            'accountFound' => true,
            'recoveryOptions' => $recoveryOptions,
            'accountName' => $accountName,
            'lookupEmail' => $lookupEmail,
        ]);
    }

    public function findAccount(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Enter your registered email address.',
            'email.email' => 'Enter a valid email address.',
        ]);

        $email = strtolower(trim($data['email']));
        $this->ensureFindIsNotRateLimited($request, $email);
        RateLimiter::hit($this->findThrottleKey($request, $email), 60);

        $found = $this->resets->findRecoveryAccount($email);

        $this->forgetFindSession($request);
        $this->forgetLookupOldInput($request);

        if (! $found) {
            // Do not withInput / old() — keep the email field empty on failure.
            return redirect()
                ->route('password.request')
                ->with('error', 'No account was found with the email address you entered.');
        }

        $request->session()->put(PasswordResetService::SESSION_ACCOUNT_ID, $found['user']->id);
        $request->session()->put(PasswordResetService::SESSION_RECOVERY_OPTIONS, $found['options']);
        $request->session()->put(PasswordResetService::SESSION_LOOKUP_EMAIL, $email);
        $request->session()->put(
            PasswordResetService::SESSION_ACCOUNT_NAME,
            trim((string) ($found['account_name'] ?: $found['user']->full_name))
        );
        $request->session()->flash(PasswordResetService::FLASH_REVEAL, true);

        return redirect()
            ->route('password.request')
            ->with('success', 'Account found. Choose where to send the password reset link.');
    }

    private function forgetFindSession(Request $request): void
    {
        $request->session()->forget([
            PasswordResetService::SESSION_EMAIL,
            PasswordResetService::SESSION_VERIFIED,
            PasswordResetService::SESSION_ACCOUNT_ID,
            PasswordResetService::SESSION_RECOVERY_OPTIONS,
            PasswordResetService::SESSION_LOOKUP_EMAIL,
            PasswordResetService::SESSION_ACCOUNT_NAME,
            PasswordResetService::FLASH_REVEAL,
        ]);
    }

    private function forgetLookupOldInput(Request $request): void
    {
        $old = $request->session()->get('_old_input');
        if (! is_array($old)) {
            return;
        }

        unset($old['email'], $old['recovery_email']);
        $request->session()->put('_old_input', $old);
    }

    private function redirectToRequestWithReveal(array $with = [])
    {
        $redirect = redirect()->route('password.request')->with(PasswordResetService::FLASH_REVEAL, true);

        foreach ($with as $key => $value) {
            $redirect = $redirect->with($key, $value);
        }

        return $redirect;
    }

    public function sendCode(Request $request)
    {
        $accountId = (int) $request->session()->get(PasswordResetService::SESSION_ACCOUNT_ID);
        $options = $request->session()->get(PasswordResetService::SESSION_RECOVERY_OPTIONS);

        if ($accountId < 1 || ! is_array($options) || $options === []) {
            $this->forgetFindSession($request);

            return redirect()
                ->route('password.request')
                ->with('error', 'Find your account first, then choose a recovery email.');
        }

        try {
            $data = $request->validate([
                'recovery_email' => ['required', 'email', 'max:255'],
            ]);

            $recoveryEmail = strtolower(trim($data['recovery_email']));

            if (! $this->resets->isAllowedRecoveryEmail($options, $recoveryEmail)) {
                throw ValidationException::withMessages([
                    'recovery_email' => 'Choose one of the recovery emails listed for this account.',
                ]);
            }

            $this->ensureSendIsNotRateLimited($request, $recoveryEmail);
            RateLimiter::hit($this->sendThrottleKey($request, $recoveryEmail), 60);

            $target = $this->resets->resolveRecoveryTarget($recoveryEmail);
            if (! $target || (int) $target['user']->id !== $accountId) {
                $this->forgetFindSession($request);

                return redirect()
                    ->route('password.request')
                    ->with('error', 'That recovery option is no longer available. Find your account again.');
            }

            $this->resets->sendCode($target['user'], $recoveryEmail);
        } catch (ValidationException $e) {
            $message = $e->errors()['recovery_email'][0]
                ?? $e->errors()['email'][0]
                ?? 'The password reset link could not be sent. Please try again.';

            return $this->redirectToRequestWithReveal([
                'error' => $message,
            ])->withErrors($e->errors());
        }

        $request->session()->put(PasswordResetService::SESSION_EMAIL, $recoveryEmail);
        $request->session()->forget(PasswordResetService::SESSION_VERIFIED);
        $request->session()->forget([
            PasswordResetService::SESSION_ACCOUNT_ID,
            PasswordResetService::SESSION_RECOVERY_OPTIONS,
            PasswordResetService::SESSION_LOOKUP_EMAIL,
            PasswordResetService::SESSION_ACCOUNT_NAME,
            PasswordResetService::FLASH_REVEAL,
        ]);

        return redirect()
            ->route('password.verify')
            ->with('success', 'A password reset link was sent to '.$recoveryEmail.'. It expires in '.PasswordResetService::EXPIRE_MINUTES.' minutes.');
    }

    public function consumeResetLink(Request $request)
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $code = (string) $request->query('code', '');

        if ($email === '' || $code === '') {
            return redirect()
                ->route('password.request')
                ->with('error', 'This password reset link is invalid.');
        }

        try {
            $this->resets->verifyCode($email, $code);
        } catch (ValidationException $e) {
            $message = $e->errors()['code'][0]
                ?? $e->errors()['email'][0]
                ?? 'This password reset link is invalid or has expired.';

            return redirect()
                ->route('password.request')
                ->with('error', $message);
        }

        $request->session()->put(PasswordResetService::SESSION_EMAIL, $email);
        $request->session()->put(PasswordResetService::SESSION_VERIFIED, true);
        $request->session()->forget([
            PasswordResetService::SESSION_ACCOUNT_ID,
            PasswordResetService::SESSION_RECOVERY_OPTIONS,
            PasswordResetService::SESSION_LOOKUP_EMAIL,
            PasswordResetService::SESSION_ACCOUNT_NAME,
        ]);

        return redirect()
            ->route('password.reset')
            ->with('success', 'Verification successful. Create a new password for this account.');
    }

    public function showVerifyForm(Request $request)
    {
        $email = $this->sessionEmail($request);

        if (! $email) {
            return redirect()
                ->route('password.request')
                ->with('error', 'Find your account and send a reset link first.');
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
            if ($this->isIncorrectCodeError($e)) {
                RateLimiter::hit($this->verifyThrottleKey($request, $email), 300);
            }

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
        RateLimiter::hit($this->sendThrottleKey($request, $email), 60);

        $target = $this->resets->resolveRecoveryTarget($email);

        if (! $target) {
            $request->session()->forget([
                PasswordResetService::SESSION_EMAIL,
                PasswordResetService::SESSION_VERIFIED,
                PasswordResetService::SESSION_ACCOUNT_ID,
                PasswordResetService::SESSION_RECOVERY_OPTIONS,
                PasswordResetService::SESSION_LOOKUP_EMAIL,
                PasswordResetService::SESSION_ACCOUNT_NAME,
            ]);

            return redirect()
                ->route('password.request')
                ->with('error', 'Find your account again to continue password recovery.');
        }

        $this->resets->sendCode($target['user'], $target['delivery_email']);
        $request->session()->forget(PasswordResetService::SESSION_VERIFIED);
        RateLimiter::clear($this->verifyThrottleKey($request, $email));

        return redirect()
            ->route('password.verify')
            ->with('success', 'A new password reset link was sent to '.$email.'.');
    }

    public function showResetForm(Request $request)
    {
        $email = $this->sessionEmail($request);

        if (! $email || ! $request->session()->get(PasswordResetService::SESSION_VERIFIED)) {
            return redirect()
                ->route('password.request')
                ->with('error', 'Open the reset link from your email or verify the code before creating a new password.');
        }

        return view('auth.reset-password', compact('email'));
    }

    public function updatePassword(Request $request)
    {
        $email = $this->requireSessionEmail($request);

        if (! $request->session()->get(PasswordResetService::SESSION_VERIFIED)) {
            return redirect()
                ->route('password.request')
                ->with('error', 'Open the reset link from your email or verify the code before creating a new password.');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'password.confirmed' => 'The new password confirmation does not match.',
            'password.min' => 'The new password must be at least 8 characters.',
        ]);

        $this->resets->resetPassword($email, $validated['password']);

        $request->session()->forget([
            PasswordResetService::SESSION_EMAIL,
            PasswordResetService::SESSION_VERIFIED,
            PasswordResetService::SESSION_ACCOUNT_ID,
            PasswordResetService::SESSION_RECOVERY_OPTIONS,
            PasswordResetService::SESSION_LOOKUP_EMAIL,
            PasswordResetService::SESSION_ACCOUNT_NAME,
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
                'email' => 'Find your account and send a reset link first.',
            ])->redirectTo(route('password.request'));
        }

        return $email;
    }

    private function findThrottleKey(Request $request, string $email): string
    {
        return 'password-reset-find|'.Str::lower($email).'|'.$request->ip();
    }

    private function sendThrottleKey(Request $request, string $email): string
    {
        return 'password-reset-send|'.Str::lower($email).'|'.$request->ip();
    }

    private function verifyThrottleKey(Request $request, string $email): string
    {
        return 'password-reset-verify|'.Str::lower($email).'|'.$request->ip();
    }

    private function ensureFindIsNotRateLimited(Request $request, string $email): void
    {
        if (RateLimiter::tooManyAttempts($this->findThrottleKey($request, $email), 5)) {
            $seconds = RateLimiter::availableIn($this->findThrottleKey($request, $email));

            throw ValidationException::withMessages([
                'email' => "Please wait {$seconds} seconds before searching again.",
            ]);
        }
    }

    private function ensureSendIsNotRateLimited(Request $request, string $email): void
    {
        if (RateLimiter::tooManyAttempts($this->sendThrottleKey($request, $email), 1)) {
            $seconds = RateLimiter::availableIn($this->sendThrottleKey($request, $email));

            throw ValidationException::withMessages([
                'recovery_email' => "Please wait {$seconds} seconds before requesting another reset link.",
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

    private function isIncorrectCodeError(ValidationException $e): bool
    {
        $messages = $e->errors()['code'] ?? [];

        foreach ($messages as $message) {
            if (str_contains(strtolower((string) $message), 'does not match')) {
                return true;
            }
        }

        return false;
    }
}
