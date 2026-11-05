<?php

namespace App\Services;

use App\Support\MailEnv;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailConfigService
{
    /**
     * Get the current email configuration status.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $mailer = strtolower(
            trim((string) config('mail.default'))
        );

        $host = trim(
            (string) config('mail.mailers.smtp.host')
        );

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

        if (
            $mailer === 'smtp'
            && $from === ''
            && $username !== ''
        ) {
            $from = $username;
        }

        /*
        |--------------------------------------------------------------------------
        | Local / Testing Mailers
        |--------------------------------------------------------------------------
        */

        if (in_array($mailer, ['array', 'log'], true)) {
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
                []
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Gmail API
        |--------------------------------------------------------------------------
        */

        if ($mailer === 'gmail_api') {
            return $this->gmailApiStatus($from);
        }

        /*
        |--------------------------------------------------------------------------
        | Resend
        |--------------------------------------------------------------------------
        */

        if ($mailer === 'resend') {
            return $this->resendStatus($from);
        }

        /*
        |--------------------------------------------------------------------------
        | Unsupported Mailer
        |--------------------------------------------------------------------------
        */

        if ($mailer !== 'smtp') {
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
                [
                    'Unsupported MAIL_MAILER. Use gmail_api, smtp, or resend.',
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SMTP
        |--------------------------------------------------------------------------
        */

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
            $this->smtpIssues(
                $host,
                $port,
                $username,
                $password,
                $from
            )
        );
    }

    /**
     * Determine whether the configured mailer has
     * its required settings.
     */
    public function isReady(): bool
    {
        return $this->status()['ready'];
    }

    /**
     * Display a helpful error when required
     * email configuration is missing.
     */
    public function notConfiguredMessage(): string
    {
        $mailer = strtolower(
            trim((string) config('mail.default'))
        );

        if ($mailer === 'gmail_api') {
            return 'Gmail API email delivery is not configured. '
                . 'Set GOOGLE_GMAIL_CLIENT_ID, '
                . 'GOOGLE_GMAIL_CLIENT_SECRET, '
                . 'GOOGLE_GMAIL_REFRESH_TOKEN, '
                . 'and MAIL_FROM_ADDRESS in Railway. '
                . 'Then redeploy the application.';
        }

        if ($mailer === 'smtp') {
            return 'SMTP email delivery is not configured. '
                . 'Check MAIL_USERNAME, MAIL_PASSWORD, '
                . 'MAIL_FROM_ADDRESS, and your SMTP settings.';
        }

        return 'Email delivery is not configured. '
            . 'On Railway, configure Gmail API or another supported '
            . 'email provider and its required credentials.';
    }

    /**
     * Convert technical mail exceptions into
     * user-friendly messages.
     */
    public function deliveryError(Throwable $e): string
    {
        $mailer = strtolower(
            trim((string) config('mail.default'))
        );

        $raw = strtolower(
            $e->getMessage()
            . ' '
            . ($e->getPrevious()?->getMessage() ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Gmail API Errors
        |--------------------------------------------------------------------------
        */

        if ($mailer === 'gmail_api') {

            if (
                str_contains($raw, 'invalid_grant')
                || str_contains($raw, 'token refresh failed')
                || str_contains($raw, 'revoked')
            ) {
                return 'Gmail API authorization has expired or '
                    . 'been revoked. Reconnect the authorized Gmail '
                    . 'account to generate a valid refresh token.';
            }

            if (
                str_contains($raw, 'invalid_client')
                || str_contains($raw, 'client secret')
                || str_contains($raw, 'client id')
                || str_contains($raw, 'oauth credentials are incomplete')
            ) {
                return 'Gmail API OAuth credentials are invalid or '
                    . 'incomplete. Check GOOGLE_GMAIL_CLIENT_ID '
                    . 'and GOOGLE_GMAIL_CLIENT_SECRET.';
            }

            if (
                str_contains($raw, 'insufficientpermissions')
                || str_contains($raw, 'insufficient authentication scopes')
            ) {
                return 'Gmail API permission is insufficient. '
                    . 'Reconnect the Gmail account and authorize '
                    . 'the gmail.send permission.';
            }

            if (
                str_contains($raw, 'sender')
                || str_contains($raw, 'from address')
            ) {
                return 'Gmail API sender address is incorrect. '
                    . 'MAIL_FROM_ADDRESS must match the Gmail account '
                    . 'used to authorize the application.';
            }

            if (
                str_contains($raw, '429')
                || str_contains($raw, 'quota')
                || str_contains($raw, 'rate limit')
            ) {
                return 'Gmail API sending limit has been reached. '
                    . 'Please wait and try again.';
            }

            return 'Gmail API could not send the email. '
                . 'Check the Google OAuth authorization, '
                . 'Gmail API configuration, and Railway logs.';
        }

        /*
        |--------------------------------------------------------------------------
        | Resend Errors
        |--------------------------------------------------------------------------
        */

        if ($mailer === 'resend') {

            if (
                str_contains($raw, 'api key')
                || str_contains($raw, 'unauthorized')
                || str_contains($raw, '401')
                || str_contains($raw, '403')
            ) {
                return 'Resend rejected the API key. '
                    . 'Set RESEND_API_KEY from the Resend dashboard, '
                    . 'then redeploy.';
            }

            if (
                str_contains($raw, 'domain')
                || str_contains($raw, 'from')
                || str_contains($raw, 'validation')
                || str_contains($raw, '422')
            ) {
                return 'Resend rejected the sending address. '
                    . 'MAIL_FROM_ADDRESS must use a domain '
                    . 'verified in Resend.';
            }

            return 'Resend could not send the email. '
                . 'Confirm RESEND_API_KEY and the verified '
                . 'sending domain, then try again.';
        }

        /*
        |--------------------------------------------------------------------------
        | SMTP Errors
        |--------------------------------------------------------------------------
        */

        if (
            str_contains($raw, 'auth')
            || str_contains($raw, '535')
            || str_contains($raw, '534')
            || str_contains($raw, 'username')
            || str_contains($raw, 'password')
            || str_contains($raw, 'credentials')
        ) {
            return 'Gmail rejected the SMTP login. '
                . 'MAIL_USERNAME and MAIL_FROM_ADDRESS must match '
                . 'the sending Gmail account. MAIL_PASSWORD must '
                . 'be a valid Google App Password.';
        }

        if (
            str_contains($raw, 'connect')
            || str_contains($raw, 'timed out')
            || str_contains($raw, 'timeout')
            || str_contains($raw, 'connection refused')
        ) {
            return 'Could not connect to Gmail SMTP. '
                . 'Railway Hobby restricts outbound SMTP connections. '
                . 'Configure MAIL_MAILER=gmail_api with valid '
                . 'OAuth credentials to use Gmail API over HTTPS.';
        }

        return 'The email could not be sent. '
            . 'Check the mail settings and try again.';
    }

    /**
     * Recover runtime credentials if the cached
     * Laravel configuration contains empty values.
     */
    public function syncRuntimeCredentials(): void
    {
        $mailer = strtolower(
            trim((string) config('mail.default'))
        );

        if (in_array($mailer, ['array', 'log', ''], true)) {
            return;
        }

        [$from] = $this->resolveCredential(
            (string) config('mail.from.address'),
            'MAIL_FROM_ADDRESS'
        );

        $updates = [];

        /*
        |--------------------------------------------------------------------------
        | Gmail API Runtime Credentials
        |--------------------------------------------------------------------------
        */

        if ($mailer === 'gmail_api') {

            $credentials = [
                'services.gmail_api.client_id'
                    => 'GOOGLE_GMAIL_CLIENT_ID',

                'services.gmail_api.client_secret'
                    => 'GOOGLE_GMAIL_CLIENT_SECRET',

                'services.gmail_api.refresh_token'
                    => 'GOOGLE_GMAIL_REFRESH_TOKEN',
            ];

            foreach ($credentials as $configKey => $envKey) {

                $configured = (string) config($configKey);

                [$resolved] = $this->resolveCredential(
                    $configured,
                    $envKey
                );

                if (
                    $resolved !== ''
                    && $resolved !== $configured
                ) {
                    $updates[$configKey] = $resolved;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SMTP Runtime Credentials
        |--------------------------------------------------------------------------
        */

        if ($mailer === 'smtp') {

            [$username] = $this->resolveCredential(
                (string) config('mail.mailers.smtp.username'),
                'MAIL_USERNAME'
            );

            [$password] = $this->resolveCredential(
                (string) config('mail.mailers.smtp.password'),
                'MAIL_PASSWORD',
                stripInternalWhitespace: true
            );

            if ($from === '' && $username !== '') {
                $from = $username;
            }

            $configuredUsername = (string) config(
                'mail.mailers.smtp.username'
            );

            if (
                $username !== ''
                && $username !== $configuredUsername
            ) {
                $updates['mail.mailers.smtp.username'] = $username;
            }

            $configuredPassword = (string) config(
                'mail.mailers.smtp.password'
            );

            if (
                $password !== ''
                && $password !== $configuredPassword
            ) {
                $updates['mail.mailers.smtp.password'] = $password;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Resend Runtime Credentials
        |--------------------------------------------------------------------------
        */

        if ($mailer === 'resend') {

            [$apiKey] = $this->resolveCredential(
                (string) config('services.resend.key'),
                'RESEND_API_KEY'
            );

            if (
                $apiKey !== ''
                && $apiKey !== (string) config('services.resend.key')
            ) {
                $updates['services.resend.key'] = $apiKey;
                $updates['mail.mailers.resend.key'] = $apiKey;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Global Sender
        |--------------------------------------------------------------------------
        */

        if (
            $from !== ''
            && trim((string) config('mail.from.address')) !== $from
        ) {
            $updates['mail.from.address'] = $from;
        }

        /*
        |--------------------------------------------------------------------------
        | Apply Configuration Updates
        |--------------------------------------------------------------------------
        */

        if ($updates !== []) {

            config($updates);

            Mail::purge($mailer);
        }
    }

    /**
     * Check Gmail API OAuth configuration.
     *
     * @return array<string, mixed>
     */
    private function gmailApiStatus(string $from): array
    {
        [$clientId, $clientIdSource] = $this->resolveCredential(
            (string) config('services.gmail_api.client_id'),
            'GOOGLE_GMAIL_CLIENT_ID'
        );

        [$clientSecret] = $this->resolveCredential(
            (string) config('services.gmail_api.client_secret'),
            'GOOGLE_GMAIL_CLIENT_SECRET'
        );

        [$refreshToken] = $this->resolveCredential(
            (string) config('services.gmail_api.refresh_token'),
            'GOOGLE_GMAIL_REFRESH_TOKEN'
        );

        $issues = $this->fromIssues($from);

        if ($clientId === '') {
            $issues[] = 'GOOGLE_GMAIL_CLIENT_ID is missing.';
        }

        if ($clientSecret === '') {
            $issues[] = 'GOOGLE_GMAIL_CLIENT_SECRET is missing.';
        }

        if ($refreshToken === '') {
            $issues[] = 'GOOGLE_GMAIL_REFRESH_TOKEN is missing. '
                . 'Complete Gmail OAuth authorization first.';
        }

        if (
            $from !== ''
            && $this->looksLikeEmail($from)
            && ! $this->isGmailAddress($from)
        ) {
            $issues[] = 'MAIL_FROM_ADDRESS must be the authorized '
                . 'Gmail address for this BSSA Gmail API setup.';
        }

        return $this->statusPayload(
            'gmail_api',
            'gmail.googleapis.com',
            443,
            'https',
            'https',
            $from,
            $clientSecret !== '' && $refreshToken !== '',
            $from,
            $clientIdSource,
            $issues
        );
    }

    /**
     * Check SMTP configuration.
     *
     * @return list<string>
     */
    private function smtpIssues(
        string $host,
        mixed $port,
        string $username,
        string $password,
        string $from
    ): array {

        $issues = [];

        $gmail = $host === 'smtp.gmail.com'
            || str_ends_with(
                strtolower($username),
                '@gmail.com'
            );

        if ($host === '') {

            $issues[] = 'MAIL_HOST is empty.';

        } elseif ($gmail && $host !== 'smtp.gmail.com') {

            $issues[] = 'MAIL_HOST should be smtp.gmail.com.';
        }

        if (
            (int) $port !== 587
            && (int) $port !== 465
        ) {
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

            $issues[] = 'MAIL_PASSWORD must be a Google App Password '
                . '(16 characters, spaces optional).';
        }

        $issues = array_merge(
            $issues,
            $this->fromIssues($from)
        );

        if (
            $gmail
            && $this->looksLikeEmail($username)
            && $this->looksLikeEmail($from)
            && strcasecmp($username, $from) !== 0
        ) {
            $issues[] = 'MAIL_FROM_ADDRESS should match '
                . 'MAIL_USERNAME for Gmail SMTP.';
        }

        return $issues;
    }

    /**
     * Check Resend configuration.
     *
     * @return array<string, mixed>
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
                : 'RESEND_API_KEY is empty. Create an API key '
                    . 'in Resend and set it in the environment.';
        }

        if ($this->isGmailAddress($from)) {
            $issues[] = 'Resend cannot send from a Gmail address. '
                . 'Use an address on a domain verified in Resend.';
        }

        return $this->statusPayload(
            'resend',
            'api.resend.com',
            443,
            'https',
            'https',
            '',
            $apiKey !== '',
            $from,
            $keySource,
            $issues
        );
    }

    /**
     * Validate the sender email address.
     *
     * @return list<string>
     */
    private function fromIssues(string $from): array
    {
        if ($from === '') {
            return [
                'MAIL_FROM_ADDRESS is empty.',
            ];
        }

        if (! $this->looksLikeEmail($from)) {
            return [
                'MAIL_FROM_ADDRESS is not a valid email address.',
            ];
        }

        return [];
    }

    /**
     * Determine whether an address belongs to Gmail.
     */
    private function isGmailAddress(string $value): bool
    {
        $value = strtolower(
            trim($value)
        );

        return str_ends_with($value, '@gmail.com')
            || str_ends_with($value, '@googlemail.com');
    }

    /**
     * Create a consistent status response for
     * all supported mailers.
     *
     * @param list<string> $issues
     * @return array<string, mixed>
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

        $passwordConfigured = is_bool($password)
            ? $password
            : $password !== '';

        return [

            'ready' => $issues === [],

            'mailer' => $mailer,

            'host' => $host,

            'port' => $port,

            'encryption' => is_string($encryption)
                ? $encryption
                : null,

            'scheme' => is_string($scheme)
                ? $scheme
                : null,

            'username_configured' => $username !== '',

            'password_configured' => $passwordConfigured,

            'from_configured' => $from !== '',

            'from_matches_username' => $username !== ''
                && strcasecmp($username, $from) === 0,

            'username_preview' => in_array(
                $mailer,
                ['resend', 'gmail_api'],
                true
            )
                ? $this->previewEmail($from)
                : $this->previewEmail($username),

            'username_source' => $source,

            'issues' => $issues,

        ];
    }

    /**
     * Resolve credentials from Laravel config,
     * environment variables, or the local .env file.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveCredential(
        string $configValue,
        string $envKey,
        bool $stripInternalWhitespace = false
    ): array {

        $fromConfig = MailEnv::string(
            $configValue,
            $stripInternalWhitespace
        );

        if ($fromConfig !== '') {
            return [$fromConfig, 'config'];
        }

        $fromEnv = MailEnv::string(
            (string) (
                $_ENV[$envKey]
                ?? $_SERVER[$envKey]
                ?? getenv($envKey)
                ?: ''
            ),
            $stripInternalWhitespace
        );

        if ($fromEnv !== '') {
            return [$fromEnv, 'env-fallback'];
        }

        $fromFile = MailEnv::fromDotenvFile(
            $envKey,
            $stripInternalWhitespace
        );

        if ($fromFile !== '') {
            return [$fromFile, 'dotenv-file'];
        }

        return ['', 'missing'];
    }

    /**
     * Validate an email address.
     */
    private function looksLikeEmail(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return (bool) preg_match(
            '/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
            $value
        );
    }

    /**
     * Hide part of an email address for display.
     */
    private function previewEmail(string $value): string
    {
        if ($value === '') {
            return '(empty)';
        }

        $at = strpos($value, '@');

        if ($at === false) {
            return '(invalid)';
        }

        return substr($value, 0, 1)
            . '***@'
            . substr($value, $at + 1);
    }
}