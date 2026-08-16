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

    'pbn' => [
        'api_timeout_seconds' => (int) env('PBN_API_TIMEOUT_SECONDS', 30),
        'delete_timeout_seconds' => (int) env('PBN_DELETE_TIMEOUT_SECONDS', 900),
        'link_delay_seconds' => (int) env('PBN_LINK_DELAY_SECONDS', 5),
        /** Default scheme when api_url has no http/https prefix (use http for sites without SSL). */
        'default_api_scheme' => env('PBN_DEFAULT_API_SCHEME', 'http'),
        /** If https fails (no SSL / bad cert), retry same path over http:// once. */
        'https_to_http_fallback' => filter_var(env('PBN_HTTPS_TO_HTTP_FALLBACK', true), FILTER_VALIDATE_BOOL),
    ],

];
