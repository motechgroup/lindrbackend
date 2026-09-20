<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'livekit' => [
        'url' => env('LIVEKIT_URL', 'wss://livekit.lindr.app'),
        'api_key' => env('LIVEKIT_API_KEY', 'devkey'),
        'api_secret' => env('LIVEKIT_API_SECRET', 'secret_key_dev_lindr_123456789'),
    ],

    'mpesa' => [
        'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE', '174379'),
        'passkey' => env('MPESA_PASSKEY'),
        'b2c_shortcode' => env('MPESA_B2C_SHORTCODE', env('MPESA_SHORTCODE', '600000')),
        'initiator_name' => env('MPESA_INITIATOR_NAME', env('MPESA_B2C_INITIATOR_NAME', 'testapi')),
        'security_credential' => env('MPESA_SECURITY_CREDENTIAL', env('MPESA_B2C_SECURITY_CREDENTIAL')),
        'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),
        'b2c_result_url' => env('MPESA_B2C_RESULT_URL'),
        'b2c_timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),
        'callback_url' => env('MPESA_CALLBACK_URL'),
    ],

    'flutterwave' => [
        'public_key' => env('FLW_PUBLIC_KEY'),
        'secret_key' => env('FLW_SECRET_KEY'),
        'webhook_secret' => env('FLW_WEBHOOK_SECRET'),
    ],
];
