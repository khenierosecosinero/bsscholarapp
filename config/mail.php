<?php

use App\Support\MailEnv;

$mailUsername = MailEnv::string(env('MAIL_USERNAME'));
$mailPassword = MailEnv::string(env('MAIL_PASSWORD'), stripInternalWhitespace: true);
$mailFrom = MailEnv::string(env('MAIL_FROM_ADDRESS', $mailUsername !== '' ? $mailUsername : 'hello@example.com'));
$mailEncryption = strtolower(MailEnv::string(env('MAIL_ENCRYPTION', 'tls')) ?: 'tls');
$mailScheme = MailEnv::string(env('MAIL_SCHEME'));

if ($mailScheme === '') {
    $mailScheme = match ($mailEncryption) {
        'ssl', 'smtps' => 'smtps',
        default => 'smtp',
    };
}

return [

    'default' => MailEnv::string(env('MAIL_MAILER', 'smtp')) ?: 'smtp',

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => $mailScheme,
            'url' => env('MAIL_URL'),
            'host' => MailEnv::string(env('MAIL_HOST', 'smtp.gmail.com')) ?: 'smtp.gmail.com',
            'port' => (int) (MailEnv::string(env('MAIL_PORT', '587')) ?: 587),
            'username' => $mailUsername,
            'password' => $mailPassword,
            'encryption' => $mailEncryption !== '' ? $mailEncryption : 'tls',
            'timeout' => null,
            'local_domain' => MailEnv::string(env('MAIL_EHLO_DOMAIN'))
                ?: (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    'from' => [
        'address' => $mailFrom !== '' ? $mailFrom : 'hello@example.com',
        'name' => MailEnv::string(env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel'))) ?: 'Laravel',
    ],

];
