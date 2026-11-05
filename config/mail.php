<?php

use App\Support\MailEnv;

$mailUsername = MailEnv::string(
    env('MAIL_USERNAME')
);

$mailPassword = MailEnv::string(
    env('MAIL_PASSWORD'),
    stripInternalWhitespace: true
);

$mailFrom = MailEnv::string(
    env(
        'MAIL_FROM_ADDRESS',
        $mailUsername !== ''
            ? $mailUsername
            : 'hello@example.com'
    )
);

$mailEncryption = strtolower(
    MailEnv::string(
        env('MAIL_ENCRYPTION', 'tls')
    ) ?: 'tls'
);

$mailScheme = MailEnv::string(
    env('MAIL_SCHEME')
);

if ($mailScheme === '') {
    $mailScheme = match ($mailEncryption) {
        'ssl', 'smtps' => 'smtps',
        default => 'smtp',
    };
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | Supported mailers include:
    |
    | smtp       - Traditional SMTP
    | gmail_api  - Gmail API using OAuth 2.0 over HTTPS
    | resend     - Resend email API
    | log        - Log emails locally
    |
    */

    'default' => MailEnv::string(
        env('MAIL_MAILER', 'smtp')
    ) ?: 'smtp',

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    */

    'mailers' => [

        /*
        |--------------------------------------------------------------------------
        | SMTP Mailer
        |--------------------------------------------------------------------------
        */

        'smtp' => [
            'transport' => 'smtp',

            'scheme' => $mailScheme,

            'url' => env('MAIL_URL'),

            'host' => MailEnv::string(
                env('MAIL_HOST', 'smtp.gmail.com')
            ) ?: 'smtp.gmail.com',

            'port' => (int) (
                MailEnv::string(
                    env('MAIL_PORT', '587')
                ) ?: 587
            ),

            'username' => $mailUsername,

            'password' => $mailPassword,

            'encryption' => $mailEncryption !== ''
                ? $mailEncryption
                : 'tls',

            'timeout' => (int) env(
                'MAIL_TIMEOUT',
                20
            ),

            'local_domain' => MailEnv::string(
                env('MAIL_EHLO_DOMAIN')
            ) ?: (
                parse_url(
                    (string) env(
                        'APP_URL',
                        'http://localhost'
                    ),
                    PHP_URL_HOST
                ) ?: 'localhost'
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | Gmail API Mailer
        |--------------------------------------------------------------------------
        |
        | Uses Google OAuth 2.0 and Gmail API over HTTPS (port 443).
        |
        | This transport does not require SMTP ports 25, 465, or 587.
        |
        | The Gmail API transport will be registered in
        | AppServiceProvider.php.
        |
        | OAuth credentials will be stored in config/services.php.
        |
        */

        'gmail_api' => [
            'transport' => 'gmail_api',
        ],

        /*
        |--------------------------------------------------------------------------
        | Amazon SES
        |--------------------------------------------------------------------------
        */

        'ses' => [
            'transport' => 'ses',
        ],

        /*
        |--------------------------------------------------------------------------
        | Postmark
        |--------------------------------------------------------------------------
        */

        'postmark' => [
            'transport' => 'postmark',
        ],

        /*
        |--------------------------------------------------------------------------
        | Resend
        |--------------------------------------------------------------------------
        */

        'resend' => [
            'transport' => 'resend',

            'key' => MailEnv::string(
                env('RESEND_API_KEY')
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | Sendmail
        |--------------------------------------------------------------------------
        */

        'sendmail' => [
            'transport' => 'sendmail',

            'path' => env(
                'MAIL_SENDMAIL_PATH',
                '/usr/sbin/sendmail -bs -i'
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | Log Mailer
        |--------------------------------------------------------------------------
        */

        'log' => [
            'transport' => 'log',

            'channel' => env(
                'MAIL_LOG_CHANNEL'
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | Array Mailer
        |--------------------------------------------------------------------------
        */

        'array' => [
            'transport' => 'array',
        ],

        /*
        |--------------------------------------------------------------------------
        | Failover Mailer
        |--------------------------------------------------------------------------
        */

        'failover' => [
            'transport' => 'failover',

            'mailers' => [
                'smtp',
                'log',
            ],

            'retry_after' => 60,
        ],

        /*
        |--------------------------------------------------------------------------
        | Round Robin Mailer
        |--------------------------------------------------------------------------
        */

        'roundrobin' => [
            'transport' => 'roundrobin',

            'mailers' => [
                'ses',
                'postmark',
            ],

            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global From Address
    |--------------------------------------------------------------------------
    |
    | Emails will be sent using the configured sender address.
    |
    */

    'from' => [

        'address' => $mailFrom !== ''
            ? $mailFrom
            : 'hello@example.com',

        'name' => MailEnv::string(
            env(
                'MAIL_FROM_NAME',
                env('APP_NAME', 'Laravel')
            )
        ) ?: 'Laravel',

    ],

];