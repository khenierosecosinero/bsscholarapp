<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file stores configuration for third-party services.
    | Sensitive credentials must be stored in environment variables.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Postmark
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resend
    |--------------------------------------------------------------------------
    */

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Amazon SES
    |--------------------------------------------------------------------------
    */

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gmail API (OAuth 2.0)
    |--------------------------------------------------------------------------
    |
    | Sends email through Gmail API using HTTPS.
    |
    | Sender:
    | batangsurigaononscholarsapp@gmail.com
    |
    | Credentials are stored in environment variables.
    | The sender address itself comes from config/mail.php.
    |
    */

    'gmail_api' => [

        'client_id' => env(
            'GOOGLE_GMAIL_CLIENT_ID'
        ),

        'client_secret' => env(
            'GOOGLE_GMAIL_CLIENT_SECRET'
        ),

        'refresh_token' => env(
            'GOOGLE_GMAIL_REFRESH_TOKEN'
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    */

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env(
                'SLACK_BOT_USER_OAUTH_TOKEN'
            ),

            'channel' => env(
                'SLACK_BOT_USER_DEFAULT_CHANNEL'
            ),
        ],
    ],

];