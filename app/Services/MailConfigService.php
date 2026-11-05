<?php

namespace App\Services;

use App\Support\MailEnv;
use Illuminate\Support\Facades\Mail;
use Throwable;

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

        // PHPUnit uses MAIL_MAILER=array. Never treat that as a provider failure.
        if (in_array($mailer, ['array', 'log'], true)) {
            return $this->statusPayload($mailer, $host, $port, $encryption, $scheme, $username, $password, $from, $usernameSource, []);
        }

        if ($mailer === 'resend') {
            return $this->resendStatus($from);
        }

        if ($mailer !== 'smtp') {
            return $this->statusPayload($mailer, $host, $port, $encryption, $scheme, $username, $password, $from, $usernameSource, [
                'MAIL_MAILER must be resend for Railway, or smtp for local Gmail.',
            ]);
        }

        return $this->statusPayload(
            $mailer,
            $host,
            $port,
            $encryption,
            $scheme,
            $username,
            $password,
            $from,
            $usernameSource,
            $this->smtpIssues($host, $port, $username, $password, $from)
        );
    }

    public function isReady(): bool
    {
        return $this->status()['ready'];
    }

    public function notConfiguredMessage(): string
    {
        return 'Email delivery is not configured. On Railway set MAIL_MAILER=resend, RESEND_API_KEY, and a MAIL_FROM_ADDRESS on a domain verified in Resend. Local Gmail can stay on MAIL_MAILER=smtp. Then run php artisan config:clear.';
    }

    public function deliveryError(Throwable $e): string
    {
        $mailer = strtolower(trim((string) config('mail.default')));
        $raw = strtolower($e->getMessage());

        if ($mailer === 'resend') {
            if (str_contains($raw, 'api key') || str_contains($raw, 'unauthorized') || str_contains($raw, '401') || str_contains($raw, '403')) {
                return 'Resend rejected the API key. Set RESEND_API_KEY from the Resend dashboard, then redeploy.';
            }

            if (str_contains($raw, 'domain') || str_contains($raw, 'from') || str_contains($raw, 'validation') || str_contains($raw, '422')) {
                return 'Resend rejected the sending address. MAIL_FROM_ADDRESS must use a domain you verified in Resend.';
            }

            return 'Resend could not send the email. Confirm RESEND_API_KEY and the verified sending domain, then try again.';
        }

        if (str_contains($raw, 'auth') || str_contains($raw, '535') || str_contains($raw, '534') || str_contains($raw, 'username') || str_contains($raw, 'password') || str_contains($raw, 'credentials')) {
            return 'Gmail rejected the SMTP login. MAIL_USERNAME and MAIL_FROM_ADDRESS must be the sending Gmail, MAIL_PASSWORD must be a Google App Password, then run php artisan config:clear.';
        }

        if (str_contains($raw, 'connect') || str_contains($raw, 'timed out') || str_contains($raw, 'timeout') || str_contains($raw, 'connection refused')) {
            return 'Could not connect to Gmail SMTP. Railway Hobby blocks outbound SMTP on ports 25, 465, and 587. Set MAIL_MAILER=resend and RESEND_API_KEY, then redeploy.';
        }

        return 'The email could not be sent. Check the mail settings and try again.';
    }

    /**
     * If a stale config:cache left mail credentials blank, copy the live
     * process environment into runtime config before a send.
     */
    public function syncRuntimeCredentials(): void
    {
        $mailer = strtolower(trim((string) config('mail.default')));

        if (in_array($mailer, ['array', 'log', ''], true)) {
            return;
        }

        [$username] = $this->resolveCredential(
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
        [$apiKey] = $this->resolveCredential(
            (string) config('services.resend.key'),
            'RESEND_API_KEY'
        );

        if ($from === '' && $mailer === 'smtp' && $username !== '') {
            $from = $username;
        }

        $updates = [];

        if ($mailer === 'smtp') {
            $configuredUsername = (string) config('mail.mailers.smtp.username');
            if ($username !== '' && $username !== $configuredUsername) {
                $updates['mail.mailers.smtp.username'] = $username;
            }

            $configuredPassword = (string) config('mail.mailers.smtp.password');
            if ($password !== '' && $password !== $configuredPassword) {
                $updates['mail.mailers.smtp.password'] = $password;
            }
        }

        if ($mailer === 'resend' && $apiKey !== '' && $apiKey !== (string) config('services.resend.key')) {
            $updates['services.resend.key'] = $apiKey;
            $updates['mail.mailers.resend.key'] = $apiKey;
        }

        if ($from !== '' && trim((string) config('mail.from.address')) !== $from) {
            $updates['mail.from.address'] = $from;
        }

        if ($updates !== []) {
            config($updates);
            Mail::purge($mailer);
        }
    }

    /**
     * @return list<string>
     */
    private function smtpIssues(string $host, mixed $port, string $username, string $password, string $from): array
    {
        $issues = [];
        $gmail = $host === 'smtp.gmail.com' || str_ends_with(strtolower($username), '@gmail.com');

        if ($host === '') {
            $issues[] = 'MAIL_HOST is empty.';
        } elseif ($gmail && $host !== 'smtp.gmail.com') {
            $issues[] = 'MAIL_HOST should be smtp.gmail.com.';
        }

        if ((int) $port !== 587 && (int) $port !== 465) {
            $issues[] = $gmail
                ? 'MAIL_PORT should be 587 (TLS) or 465 (SSL).'
                : 'MAIL_PORT should be the SMTP port for this provider.';
        }

        if ($username === '') {
            $issues[] = app()->configurationIsCached()
                ? 'MAIL_USERNAME is empty in the cached config. Run: php artisan config:clear'
                : 'MAIL_USERNAME is empty.';
        } elseif (! $this->looksLikeEmail($username)) {
            $issues[] = 'MAIL_USERNAME is not a valid email address.';
        }

        if ($password === '') {
            $issues[] = $gmail
                ? 'MAIL_PASSWORD is empty. Set a Google App Password in .env.'
                : 'MAIL_PASSWORD is empty.';
        } elseif ($gmail && strlen($password) < 16) {
            $issues[] = 'MAIL_PASSWORD must be a Google App Password (16 characters, spaces optional).';
        }

        $issues = array_merge($issues, $this->fromIssues($from));

        if ($gmail && $this->looksLikeEmail($username) && $this->looksLikeEmail($from) && strcasecmp($username, $from) !== 0) {
            $issues[] = 'MAIL_FROM_ADDRESS should match MAIL_USERNAME for Gmail SMTP.';
        }

        return $issues;
    }

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
    private function resendStatus(string $from): array
    {
        [$apiKey, $keySource] = $this->resolveCredential(
            (string) config('services.resend.key'),
            'RESEND_API_KEY'
        );

        $issues = $this->fromIssues($from);

        if ($apiKey === '') {
            $issues[] = app()->configurationIsCached()
                ? 'RESEND_API_KEY is empty in the cached config. Run: php artisan config:clear'
                : 'RESEND_API_KEY is empty. Create an API key in Resend and set it in the environment.';
        }

        if ($this->isGmailAddress($from)) {
            $issues[] = 'Resend cannot send from a Gmail address. Use an address on a domain verified in Resend.';
        }

        return $this->statusPayload('resend', 'api.resend.com', 443, 'https', 'https', '', $apiKey !== '', $from, $keySource, $issues);
    }

    /**
     * @return list<string>
     */
    private function fromIssues(string $from): array
    {
        if ($from === '') {
            return ['MAIL_FROM_ADDRESS is empty.'];
        }

        if (! $this->looksLikeEmail($from)) {
            return ['MAIL_FROM_ADDRESS is not a valid email address.'];
        }

        return [];
    }

    private function isGmailAddress(string $value): bool
    {
        $value = strtolower(trim($value));

        return str_ends_with($value, '@gmail.com') || str_ends_with($value, '@googlemail.com');
    }

    /**
     * @param  list<string>  $issues
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
    private function statusPayload(
        string $mailer,
        string $host,
        mixed $port,
        mixed $encryption,
        mixed $scheme,
        string $username,
        string|bool $password,
        string $from,
        string $source,
        array $issues
    ): array {
        $passwordConfigured = is_bool($password) ? $password : $password !== '';

        return [
            'ready' => $issues === [],
            'mailer' => $mailer,
            'host' => $host,
            'port' => $port,
            'encryption' => is_string($encryption) ? $encryption : null,
            'scheme' => is_string($scheme) ? $scheme : null,
            'username_configured' => $username !== '',
            'password_configured' => $passwordConfigured,
            'from_configured' => $from !== '',
            'from_matches_username' => $username !== '' && strcasecmp($username, $from) === 0,
            'username_preview' => $mailer === 'resend' ? $this->previewEmail($from) : $this->previewEmail($username),
            'username_source' => $source,
            'issues' => $issues,
        ];
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
