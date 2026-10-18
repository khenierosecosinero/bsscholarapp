<?php

namespace App\Services;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Support\OutboundAppUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetService
{
    public const EXPIRE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const SESSION_EMAIL = 'password_reset_email';

    public const SESSION_VERIFIED = 'password_reset_verified';

    public const SESSION_ACCOUNT_ID = 'password_reset_account_id';

    public const SESSION_RECOVERY_OPTIONS = 'password_reset_recovery_options';

    public const SESSION_LOOKUP_EMAIL = 'password_reset_lookup_email';

    /**
     * Look up an eligible account and list verified recovery destinations.
     *
     * @return array{user: User, options: list<array{email: string, label: string, type: string}>}|null
     */
    public function findRecoveryAccount(string $email): ?array
    {
        $target = $this->resolveRecoveryTarget($email);
        if (! $target) {
            return null;
        }

        $user = $target['user'];
        $options = [];
        $seen = [];

        $primary = strtolower(trim((string) $user->email));
        if ($primary !== '') {
            $options[] = [
                'email' => $primary,
                'label' => 'Primary email',
                'type' => 'primary',
            ];
            $seen[$primary] = true;
        }

        if ($user->isAdmin()) {
            foreach ($user->verifiedBackupEmailAddresses() as $backup) {
                $backup = strtolower(trim($backup));
                if ($backup === '' || isset($seen[$backup])) {
                    continue;
                }
                $options[] = [
                    'email' => $backup,
                    'label' => 'Verified backup email',
                    'type' => 'backup',
                ];
                $seen[$backup] = true;
            }
        }

        if ($options === []) {
            return null;
        }

        return [
            'user' => $user,
            'options' => $options,
        ];
    }

    /**
     * @param  list<array{email: string, label: string, type: string}>  $options
     */
    public function isAllowedRecoveryEmail(array $options, string $email): bool
    {
        $email = strtolower(trim($email));

        foreach ($options as $option) {
            if (strcasecmp((string) ($option['email'] ?? ''), $email) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve an eligible account for password recovery.
     * Scholars/staff use their primary email. Administrators may use their
     * primary email or a verified backup recovery email.
     *
     * @return array{user: User, delivery_email: string}|null
     */
    public function resolveRecoveryTarget(string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        $scholarOrStaff = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereIn('role', [User::ROLE_SCHOLAR, User::ROLE_SCHOLAR_STAFF])
            ->first();

        if ($scholarOrStaff) {
            return [
                'user' => $scholarOrStaff,
                'delivery_email' => $email,
            ];
        }

        $adminByPrimary = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where(function ($query) {
                $query->where('is_admin', true)->orWhere('role', User::ROLE_ADMIN);
            })
            ->first();

        if ($adminByPrimary) {
            return [
                'user' => $adminByPrimary,
                'delivery_email' => $email,
            ];
        }

        $adminByBackup = app(AdminBackupEmailService::class)->findVerifiedAdminByEmail($email);

        if ($adminByBackup) {
            return [
                'user' => $adminByBackup,
                'delivery_email' => $email,
            ];
        }

        return null;
    }

    public function eligibleUser(string $email): ?User
    {
        $target = $this->resolveRecoveryTarget($email);

        return $target['user'] ?? null;
    }

    public function sendCode(User $user, ?string $deliveryEmail = null): void
    {
        $email = strtolower(trim($deliveryEmail ?: (string) $user->email));
        $mailConfig = app(MailConfigService::class);
        $mailConfig->syncRuntimeCredentials();
        $mailStatus = $mailConfig->status();

        if (! $mailStatus['ready']) {
            Log::warning('Password reset email blocked: SMTP is not configured.', [
                'mailer' => $mailStatus['mailer'],
                'username_preview' => $mailStatus['username_preview'] ?? null,
                'username_source' => $mailStatus['username_source'] ?? null,
                'config_cached' => app()->configurationIsCached(),
                'issues' => $mailStatus['issues'],
            ]);

            throw ValidationException::withMessages([
                'email' => 'Email delivery is not configured. Ask an administrator to set Gmail SMTP (MAIL_USERNAME, Google App Password, MAIL_FROM_ADDRESS) in the environment and run php artisan config:clear.',
            ]);
        }

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

        $resetUrl = OutboundAppUrl::withRoot(function () use ($email, $code) {
            return URL::temporarySignedRoute(
                'password.reset.link',
                now()->addMinutes(self::EXPIRE_MINUTES),
                [
                    'email' => $email,
                    'code' => $code,
                ]
            );
        });

        try {
            Mail::mailer('smtp')->to($email)->send(new PasswordResetCodeMail($user, $code, $resetUrl));

            Log::info('Password reset email sent via SMTP.', [
                'mailer' => 'smtp',
                'host' => $mailStatus['host'],
                'port' => $mailStatus['port'],
                'username_preview' => $mailStatus['username_preview'] ?? null,
                'username_source' => $mailStatus['username_source'] ?? null,
                'recipient_domain' => substr((string) strrchr($email, '@'), 1),
                'link_host' => parse_url($resetUrl, PHP_URL_HOST),
                'queued' => false,
            ]);
        } catch (Throwable $e) {
            Log::error('Password reset email failed at SMTP transport.', [
                'mailer' => 'smtp',
                'host' => $mailStatus['host'],
                'port' => $mailStatus['port'],
                'encryption' => $mailStatus['encryption'],
                'username_preview' => $mailStatus['username_preview'] ?? null,
                'exception' => $e::class,
                'hint' => $this->safeMailError($e),
            ]);

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw ValidationException::withMessages([
                'recovery_email' => $this->safeMailError($e),
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

        if ($code === '' || ! Hash::check($code, $row->token)) {
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

        if (Hash::check($plainPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Choose a new password that is different from your current password.',
            ]);
        }

        $user->updatePassword($plainPassword);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return $user->fresh();
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
                'email' => 'Start password recovery by entering a valid email address.',
            ]);
        }

        return $user;
    }

    private function tokenRow(string $email): ?object
    {
        return DB::table('password_reset_tokens')
            ->where('email', strtolower(trim($email)))
            ->first();
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
            return 'Gmail rejected the SMTP login. MAIL_USERNAME and MAIL_FROM_ADDRESS must be the sending Gmail, MAIL_PASSWORD must be a Google App Password, then run php artisan config:clear.';
        }

        if (str_contains($raw, 'connect') || str_contains($raw, 'timed out') || str_contains($raw, 'timeout') || str_contains($raw, 'connection refused')) {
            return 'Could not connect to Gmail SMTP. Confirm MAIL_HOST=smtp.gmail.com, MAIL_PORT=587, and MAIL_ENCRYPTION=tls.';
        }

        return 'The verification code could not be sent through Gmail SMTP.';
    }
}
