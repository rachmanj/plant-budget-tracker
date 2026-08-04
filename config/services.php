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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'arkfleet' => [
        'base_url' => env('ARKFLEET_API_URL', 'http://arkfleet-next.local/api/v1'),
        'token' => env('ARKFLEET_API_TOKEN'),
        'timeout' => env('ARKFLEET_TIMEOUT', 10),
        'retries' => env('ARKFLEET_RETRIES', 2),
        'cache_ttl' => [
            'list' => env('ARKFLEET_CACHE_TTL_LIST', 3600),
            'detail' => env('ARKFLEET_CACHE_TTL_DETAIL', 21600),
            'stats' => env('ARKFLEET_CACHE_TTL_STATS', 1800),
        ],
    ],

    'sap' => [
        'server_url' => env('SAP_SERVER_URL', 'https://arkasrv2:50000'),
        'db_name' => env('SAP_DB_NAME'),
        'user' => env('SAP_USER'),
        'password' => env('SAP_PASSWORD'),
        'verify_ssl' => env('SAP_SERVICE_LAYER_VERIFY_SSL', false),
    ],

];
