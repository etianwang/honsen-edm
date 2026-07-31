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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'cos' => [
        'secret_id' => env('COS_SECRET_ID'),
        'secret_key' => env('COS_SECRET_KEY'),
        'region' => env('COS_REGION', 'ap-guangzhou'),
        'bucket' => env('COS_BUCKET'),
        'sign_url_ttl' => env('COS_SIGN_URL_TTL', 600),
        'sts_role_arn' => env('COS_STS_ROLE_ARN'),
        'sts_duration' => env('COS_STS_DURATION', 1800),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
