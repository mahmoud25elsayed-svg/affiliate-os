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

    'hostinger' => [
        'api_token' => env('HOSTINGER_API_TOKEN', ''),
        'base_url' => env('HOSTINGER_BASE_URL', 'https://api.hostinger.com/v1'),
        'parent_domain' => env('HOSTINGER_PARENT_DOMAIN', 'affiliate-os.com'),
        'username' => env('HOSTINGER_USERNAME', 'u123456789'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'),
    ],

    'smsmisr' => [
        'username' => env('SMSMISR_USERNAME', ''),
        'password' => env('SMSMISR_PASSWORD', ''),
        'sender_id' => env('SMSMISR_SENDER_ID', 'AffiliateOS'),
        'endpoint' => env('SMSMISR_ENDPOINT', 'https://smsmisr.com/api/v2/'),
    ],

    'whatsapp' => [
        'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
    ],

    'paymob' => [
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
    ],

    // Token that unlocks verbose /health telemetry. Empty disables token access (platform admins still see it).
    'health' => [
        'token' => env('HEALTH_CHECK_TOKEN', ''),
        'account_storage_limit_mb' => env('ACCOUNT_STORAGE_LIMIT_MB'),
    ],

];
