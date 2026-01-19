<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'base_url' => env('WHATSAPP_API_URL'),
        'api_key' => env('WHATSAPP_API_KEY'),
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        'gowa' => [
            'base_url' => env('WHATSAPP_GOWA_BASE_URL'),
            'username' => env('WHATSAPP_GOWA_USERNAME'),
            'password' => env('WHATSAPP_GOWA_PASSWORD'),
            'timeout' => env('WHATSAPP_GOWA_TIMEOUT', 30),
            'retry_times' => env('WHATSAPP_GOWA_RETRY_TIMES', 3),
            'retry_sleep' => env('WHATSAPP_GOWA_RETRY_SLEEP', 1000),
        ],
    ],

    's3' => [
        'timeout' => env('AWS_S3_TIMEOUT', 60),
        'connect_timeout' => env('AWS_S3_CONNECT_TIMEOUT', 10),
    ],

    'monitoring' => [
        'sentry' => [
            'dsn' => env('SENTRY_LARAVEL_DSN'),
            'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
            'environment' => env('APP_ENV', 'production'),
        ],
        'flare' => [
            'key' => env('FLARE_KEY'),
        ],
    ],

];
