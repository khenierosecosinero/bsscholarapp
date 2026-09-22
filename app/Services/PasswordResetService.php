<?php

namespace App\Services;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetService
{
    public const EXPIRE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const SESSION_EMAIL = 'password_reset_email';

    public const SESSION_VERIFIED = 'password_reset_verified';

    public function eligibleUser(string $email): ?User
    {
        return User::query()
            ->where('email', strtolower(trim($email)))
            ->whereIn('role', [User::ROLE_SCHOLAR, User::ROLE_SCHOLAR_STAFF])
            ->first();
    }

    public function sendCode(User $user): void
    {
        $email = strtolower(trim((string) $user->email));
        $code = $this->generateCode();

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
                'used_at' => null,
                'attempts' => 0,
            ]
        );

        try {
            Mail::to($email)->send(new PasswordResetCodeMail($user, $code));
        } catch (Throwable $e) {
            Log::error('Password reset email failed', [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
            ]);

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw ValidationException::withMessages([
                'email' => $this->safeMailError($e),
            ]);
        }
    }

    public function verifyCode(string $email, string $code): User
    {
        $email = strtolower(trim($email));
        $code = preg_replace('/\s+/', '', $code) ?? '';
        $user = $this->requireEligibleUser($email);
        $row = $this->tokenRow($email);

        if (! $row) {
            throw ValidationException::withMessages([
                'code' => 'No verification code was found for this email. Request a new code.',
            ]);
        }

        if ($row->used_at !== null) {
            throw ValidationException::withMessages([
                'code' => 'This verification code has already been used. Request a new code.',
            ]);
        }

        if ($this->isExpired($row)) {
            throw ValidationException::withMessages([
                'code' => 'This verification code has expired. Request a new code.',
            ]);
        }

        if ((int) $row->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => 'Too many incorrect verification attempts. Request a new code.',
            ]);
        }

        if (! Hash::check($code, $row->token)) {
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->update(['attempts' => (int) $row->attempts + 1]);

            throw ValidationException::withMessages([
                'code' => 'The verification code does not match the code sent to your email.',
            ]);
        }

        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->update(['used_at' => now()]);

        return $user;
    }

    public function resetPassword(string $email, string $plainPassword): User
    {
        $email = strtolower(trim($email));
        $user = $this->requireEligibleUser($email);
        $row = $this->tokenRow($email);

        if (! $row || $row->used_at === null) {
            throw ValidationException::withMessages([
                'password' => 'Verify the code sent to your email before creating a new password.',
            ]);
        }

        if (now()->greaterThan(Carbon::parse($row->used_at)->addMinutes(self::EXPIRE_MINUTES))) {
            throw ValidationException::withMessages([
                'password' => 'Your verification session has expired. Start the password reset again.',
            ]);
        }

        $user->updatePassword($plainPassword);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return $user;
    }

    public function forgetToken(string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('email', strtolower(trim($email)))
            ->delete();
    }

    private function requireEligibleUser(string $email): User
    {
        $user = $this->eligibleUser($email);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'This email is not registered as a Scholar or Scholar Staff account.',
            ]);
        }

        return $user;
    }

    private function tokenRow(string $email): ?object
    {
        return DB::table('password_reset_tokens')->where('email', $email)->first();
    }

    private function isExpired(object $row): bool
    {
        if ($row->created_at === null) {
            return true;
        }

        return now()->greaterThan(Carbon::parse($row->created_at)->addMinutes(self::EXPIRE_MINUTES));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function safeMailError(Throwable $e): string
    {
        $raw = strtolower($e->getMessage());

        if (str_contains($raw, 'auth') || str_contains($raw, '535') || str_contains($raw, '534') || str_contains($raw, 'username') || str_contains($raw, 'password') || str_contains($raw, 'credentials')) {
            return 'Gmail rejected the SMTP login. MAIL_USERNAME and MAIL_FROM_ADDRESS must be the sending Gmail, and MAIL_PASSWORD must be a Google App Password.';
        }

        if (str_contains($raw, 'connect') || str_contains($raw, 'timed out') || str_contains($raw, 'timeout') || str_contains($raw, 'connection refused')) {
            return 'Could not connect to Gmail SMTP. Confirm MAIL_HOST=smtp.gmail.com, MAIL_PORT=587, and MAIL_ENCRYPTION=tls.';
        }

        return 'The verification code could not be sent through Gmail SMTP.';
    }
}
