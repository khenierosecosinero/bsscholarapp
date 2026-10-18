<?php

namespace App\Services;

use App\Mail\AdminBackupEmailVerificationMail;
use App\Models\AdminBackupEmail;
use App\Models\User;
use App\Support\OutboundAppUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminBackupEmailService
{
    public const EXPIRE_MINUTES = 60;

    public function listFor(User $admin): Collection
    {
        return $admin->backupEmails()
            ->orderByRaw('CASE WHEN verified_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('email')
            ->get();
    }

    public function add(User $admin, string $email): AdminBackupEmail
    {
        $this->assertAuthorizedAdmin($admin);
        $this->ensureNotRateLimited($admin, 'add');

        $email = strtolower(trim($email));
        $this->assertAvailableEmail($admin, $email);

        $plainToken = Str::random(64);

        $backup = $admin->backupEmails()->create([
            'email' => $email,
            'verified_at' => null,
            'verify_token' => Hash::make($plainToken),
            'verify_sent_at' => now(),
        ]);

        $this->sendVerificationMail($admin, $backup, $plainToken);
        RateLimiter::hit($this->throttleKey($admin, 'add'), 60);

        return $backup->fresh();
    }

    public function update(User $admin, AdminBackupEmail $backup, string $email): AdminBackupEmail
    {
        $this->assertOwnsBackup($admin, $backup);

        $email = strtolower(trim($email));
        $this->assertAvailableEmail($admin, $email, $backup->id);

        if (strcasecmp($backup->normalizedEmail(), $email) === 0) {
            if ($backup->isVerified()) {
                return $backup;
            }

            return $this->resendVerification($admin, $backup);
        }

        $this->ensureNotRateLimited($admin, 'update');

        $plainToken = Str::random(64);

        $backup->forceFill([
            'email' => $email,
            'verified_at' => null,
            'verify_token' => Hash::make($plainToken),
            'verify_sent_at' => now(),
        ])->save();

        $this->sendVerificationMail($admin, $backup->fresh(), $plainToken);
        RateLimiter::hit($this->throttleKey($admin, 'update'), 60);

        return $backup->fresh();
    }

    public function remove(User $admin, AdminBackupEmail $backup): void
    {
        $this->assertOwnsBackup($admin, $backup);

        $backup->delete();
    }

    public function resendVerification(User $admin, AdminBackupEmail $backup): AdminBackupEmail
    {
        $this->assertOwnsBackup($admin, $backup);
        $this->ensureNotRateLimited($admin, 'resend');

        if ($backup->isVerified()) {
            throw ValidationException::withMessages([
                'backup_email' => 'That backup email is already verified.',
            ]);
        }

        $plainToken = Str::random(64);

        $backup->forceFill([
            'verify_token' => Hash::make($plainToken),
            'verify_sent_at' => now(),
        ])->save();

        $this->sendVerificationMail($admin, $backup->fresh(), $plainToken);
        RateLimiter::hit($this->throttleKey($admin, 'resend'), 60);

        return $backup->fresh();
    }

    public function verifyFromSignedLink(AdminBackupEmail $backup, string $plainToken): AdminBackupEmail
    {
        $admin = $backup->user;

        if (! $admin || ! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'backup_email' => 'This verification link is not valid.',
            ]);
        }

        $storedHash = (string) ($backup->verify_token ?? '');

        if ($backup->isVerified() || $storedHash === '' || $plainToken === '') {
            throw ValidationException::withMessages([
                'backup_email' => 'This verification link is invalid or has already been used.',
            ]);
        }

        if ($backup->verify_sent_at === null
            || now()->greaterThan($backup->verify_sent_at->copy()->addMinutes(self::EXPIRE_MINUTES))) {
            throw ValidationException::withMessages([
                'backup_email' => 'This verification link has expired. Request a new one from Admin Settings.',
            ]);
        }

        if (! Hash::check($plainToken, $storedHash)) {
            throw ValidationException::withMessages([
                'backup_email' => 'This verification link is invalid or has already been used.',
            ]);
        }

        $this->assertAvailableEmail($admin, $backup->normalizedEmail(), $backup->id);

        $backup->forceFill([
            'verified_at' => now(),
            'verify_token' => null,
            'verify_sent_at' => null,
        ])->save();

        return $backup->fresh();
    }

    /**
     * Ensure the designated initial backup address exists for the permanent admin
     * without enabling recovery until verified. Never duplicates an existing row.
     */
    public function stageDefaultPermanentAdminBackup(User $admin, bool $sendMail = true): ?AdminBackupEmail
    {
        if (! $admin->isPermanentAdmin()) {
            return null;
        }

        $email = strtolower(User::PERMANENT_ADMIN_BACKUP_EMAIL);

        $existing = $admin->backupEmails()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing) {
            return $existing;
        }

        $plainToken = Str::random(64);

        $backup = $admin->backupEmails()->create([
            'email' => $email,
            'verified_at' => null,
            'verify_token' => Hash::make($plainToken),
            'verify_sent_at' => now(),
        ]);

        if ($sendMail) {
            try {
                $this->sendVerificationMail($admin, $backup, $plainToken);
            } catch (Throwable $e) {
                Log::warning('Could not send initial admin backup email verification.', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $backup->fresh();
    }

    public function findVerifiedAdminByEmail(string $email): ?User
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $backup = AdminBackupEmail::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('verified_at')
            ->whereHas('user', function ($query) {
                $query->where(function ($admin) {
                    $admin->where('is_admin', true)->orWhere('role', User::ROLE_ADMIN);
                });
            })
            ->with('user')
            ->first();

        return $backup?->user;
    }

    private function sendVerificationMail(User $admin, AdminBackupEmail $backup, string $plainToken): void
    {
        $mailConfig = app(MailConfigService::class);
        $mailConfig->syncRuntimeCredentials();
        $mailStatus = $mailConfig->status();

        if (! $mailStatus['ready']) {
            throw ValidationException::withMessages([
                'backup_email' => 'Email delivery is not configured. Set Gmail SMTP in the environment before verifying a backup email.',
            ]);
        }

        // Email clients open links off this machine; never embed loopback APP_URL.
        $url = OutboundAppUrl::withRoot(function () use ($backup, $plainToken) {
            return URL::temporarySignedRoute(
                'admin.backup-email.verify',
                now()->addMinutes(self::EXPIRE_MINUTES),
                [
                    'backupEmail' => $backup->getKey(),
                    'token' => $plainToken,
                ]
            );
        });

        Log::info('Admin backup email verification link generated.', [
            'backup_email_id' => $backup->getKey(),
            'link_host' => parse_url($url, PHP_URL_HOST),
            'outbound_root' => OutboundAppUrl::root(),
        ]);

        try {
            Mail::mailer('smtp')->to($backup->normalizedEmail())->send(
                new AdminBackupEmailVerificationMail($admin, $backup->normalizedEmail(), $url, self::EXPIRE_MINUTES)
            );
        } catch (Throwable $e) {
            Log::error('Admin backup email verification mail failed.', [
                'exception' => $e::class,
                'hint' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'backup_email' => 'The verification email could not be sent through Gmail SMTP. Try again after confirming mail settings.',
            ]);
        }
    }

    private function assertAuthorizedAdmin(User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'backup_email' => 'Only an authorized administrator can manage backup recovery emails.',
            ]);
        }
    }

    private function assertOwnsBackup(User $admin, AdminBackupEmail $backup): void
    {
        $this->assertAuthorizedAdmin($admin);

        if ((int) $backup->user_id !== (int) $admin->id) {
            abort(403);
        }
    }

    private function assertAvailableEmail(User $admin, string $email, ?int $ignoreId = null): void
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'backup_email' => 'Enter a valid backup recovery email address.',
            ]);
        }

        if (strcasecmp($email, (string) $admin->email) === 0) {
            throw ValidationException::withMessages([
                'backup_email' => 'The backup recovery email must be different from your primary administrator email.',
            ]);
        }

        $takenByUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if ($takenByUser) {
            throw ValidationException::withMessages([
                'backup_email' => 'That email address is already used by another account.',
            ]);
        }

        $takenByBackup = AdminBackupEmail::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($takenByBackup) {
            throw ValidationException::withMessages([
                'backup_email' => 'That backup email is already configured.',
            ]);
        }
    }

    private function ensureNotRateLimited(User $admin, string $action): void
    {
        $key = $this->throttleKey($admin, $action);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'backup_email' => "Please wait {$seconds} seconds before trying again.",
            ]);
        }
    }

    private function throttleKey(User $admin, string $action): string
    {
        return 'admin-backup-email|'.$action.'|'.$admin->getKey();
    }
}
