<?php

namespace App\Services;

use App\Support\MailEnv;

class MailConfigService
{
    /**
     * @return array{
     *     ready: bool,
     *     mailer: string,
     *     host: string,
     *     port: int|string|null,
     *     encryption: string|null,
     *     scheme: string|null,
     *     username_configured: bool,
     *     password_configured: bool,
     *     from_configured: bool,
     *     from_matches_username: bool,
     *     username_preview: string,
     *     username_source: string,
     *     issues: list<string>
     * }
     */
    public function status(): array
    {
        $mailer = strtolower(trim((string) config('mail.default')));
        $host = trim((string) config('mail.mailers.smtp.host'));
        $port = config('mail.mailers.smtp.port');
        $encryption = config('mail.mailers.smtp.encryption');
        $scheme = config('mail.mailers.smtp.scheme');

        [$username, $usernameSource] = $this->resolveCredential(
            (string) config('mail.mailers.smtp.username'),
            'MAIL_USERNAME'
        );
        [$password] = $this->resolveCredential(
            (string) config('mail.mailers.smtp.password'),
            'MAIL_PASSWORD',
            stripInternalWhitespace: true
        );
        [$from] = $this->resolveCredential(
            (string) config('mail.from.address'),
            'MAIL_FROM_ADDRESS'
        );

        if ($from === '' && $username !== '') {
            $from = $username;
        }

        // PHPUnit uses MAIL_MAILER=array. Never treat that as a Gmail username failure.
        if (in_array($mailer, ['array', 'log'], true)) {
            return [
                'ready' => true,
                'mailer' => $mailer,
                'host' => $host,
                'port' => $port,
                'encryption' => is_string($encryption) ? $encryption : null,
                'scheme' => is_string($scheme) ? $scheme : null,
                'username_configured' => $username !== '',
                'password_configured' => $password !== '',
                'from_configured' => $from !== '',
                'from_matches_username' => $username !== '' && strcasecmp($username, $from) === 0,
                'username_preview' => $this->previewEmail($username),
                'username_source' => $usernameSource,
                'issues' => [],
            ];
        }

        $issues = [];

        if ($mailer !== 'smtp') {
            $issues[] = 'MAIL_MAILER should be smtp for Gmail delivery.';
        }

        if ($host !== 'smtp.gmail.com') {
            $issues[] = 'MAIL_HOST should be smtp.gmail.com.';
        }

        if ((int) $port !== 587 && (int) $port !== 465) {
            $issues[] = 'MAIL_PORT should be 587 (TLS) or 465 (SSL).';
        }

        if ($username === '') {
            $issues[] = app()->configurationIsCached()
                ? 'MAIL_USERNAME is empty in the cached config. Run: php artisan config:clear'
                : 'MAIL_USERNAME is empty. Set it to batangsurigaononscholarsapp@gmail.com in .env.';
        } elseif (! $this->looksLikeEmail($username)) {
            $issues[] = 'MAIL_USERNAME is not a valid email address.';
        }

        if ($password === '') {
            $issues[] = 'MAIL_PASSWORD is empty. Set a Google App Password in .env.';
        } elseif (strlen($password) < 16) {
            $issues[] = 'MAIL_PASSWORD must be a Google App Password (16 characters, spaces optional).';
        }

        if ($from === '') {
            $issues[] = 'MAIL_FROM_ADDRESS is empty.';
        } elseif (! $this->looksLikeEmail($from)) {
            $issues[] = 'MAIL_FROM_ADDRESS is not a valid email address.';
        }

        if ($this->looksLikeEmail($username) && $this->looksLikeEmail($from) && strcasecmp($username, $from) !== 0) {
            $issues[] = 'MAIL_FROM_ADDRESS should match MAIL_USERNAME for Gmail SMTP.';
        }

        return [
            'ready' => $issues === [],
            'mailer' => $mailer,
            'host' => $host,
            'port' => $port,
            'encryption' => is_string($encryption) ? $encryption : null,
            'scheme' => is_string($scheme) ? $scheme : null,
            'username_configured' => $username !== '',
            'password_configured' => $password !== '',
            'from_configured' => $from !== '',
            'from_matches_username' => $username !== '' && strcasecmp($username, $from) === 0,
            'username_preview' => $this->previewEmail($username),
            'username_source' => $usernameSource,
            'issues' => $issues,
        ];
    }

    public function isReady(): bool
    {
        return $this->status()['ready'];
    }

    /**
     * If a stale config:cache left SMTP username/password blank, copy the live
     * process environment values into runtime config so Symfony Mailer can auth.
     */
    public function syncRuntimeCredentials(): void
    {
        if (strtolower(trim((string) config('mail.default'))) !== 'smtp') {
            return;
        }

        [$username, $usernameSource] = $this->resolveCredential(
            (string) config('mail.mailers.smtp.username'),
            'MAIL_USERNAME'
        );
        [$password, $passwordSource] = $this->resolveCredential(
            (string) config('mail.mailers.smtp.password'),
            'MAIL_PASSWORD',
            stripInternalWhitespace: true
        );
        [$from] = $this->resolveCredential(
            (string) config('mail.from.address'),
            'MAIL_FROM_ADDRESS'
        );

        if ($from === '' && $username !== '') {
            $from = $username;
        }

        $updates = [];

        if ($username !== '' && in_array($usernameSource, ['env-fallback', 'dotenv-file'], true)) {
            $updates['mail.mailers.smtp.username'] = $username;
        }

        if ($password !== '' && in_array($passwordSource, ['env-fallback', 'dotenv-file'], true)) {
            $updates['mail.mailers.smtp.password'] = $password;
        }

        if ($from !== '' && trim((string) config('mail.from.address')) === '') {
            $updates['mail.from.address'] = $from;
        }

        if ($updates !== []) {
            config($updates);
        }
    }

    /**
     * Prefer config() (including cached config), but if the value is empty fall
     * back to the process environment. That recovers from a stale config:cache
     * that baked in blank MAIL_* values while .env is actually correct.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveCredential(string $configValue, string $envKey, bool $stripInternalWhitespace = false): array
    {
        $fromConfig = MailEnv::string($configValue, $stripInternalWhitespace);
        if ($fromConfig !== '') {
            return [$fromConfig, 'config'];
        }

        $fromEnv = MailEnv::string(
            (string) ($_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey) ?: ''),
            $stripInternalWhitespace
        );

        if ($fromEnv !== '') {
            return [$fromEnv, 'env-fallback'];
        }

        // Last resort: read .env directly. When `php artisan optimize` / config:cache
        // is active, Laravel skips Dotenv, so $_ENV may be empty even though .env is correct.
        $fromFile = MailEnv::fromDotenvFile($envKey, $stripInternalWhitespace);
        if ($fromFile !== '') {
            return [$fromFile, 'dotenv-file'];
        }

        return ['', 'missing'];
    }

    private function looksLikeEmail(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $value);
    }

    private function previewEmail(string $value): string
    {
        if ($value === '') {
            return '(empty)';
        }

        $at = strpos($value, '@');
        if ($at === false) {
            return '(invalid)';
        }

        return substr($value, 0, 1).'***@'.substr($value, $at + 1);
    }
}
