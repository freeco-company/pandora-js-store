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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:3000/auth/google/callback'),
    ],

    'line' => [
        'client_id' => env('LINE_CHANNEL_ID'),
        'client_secret' => env('LINE_CHANNEL_SECRET'),
        'redirect' => env('LINE_REDIRECT_URI', 'https://pandora-dev.js-store.com.tw/api/auth/line/callback'),
    ],

    'discord' => [
        'compliance_webhook' => env('DISCORD_COMPLIANCE_WEBHOOK'),
        'orders_webhook'     => env('DISCORD_ORDERS_WEBHOOK'),
    ],

    'ecpay' => [
        'merchant_id' => env('ECPAY_MERCHANT_ID'),
        'hash_key' => env('ECPAY_HASH_KEY'),
        'hash_iv' => env('ECPAY_HASH_IV'),
        'mode' => env('ECPAY_MODE', 'sandbox'),
        'frontend_url' => env('FRONTEND_URL', 'https://pandora.js-store.com.tw'),
        // Sender info for CVS logistics (Express/Create). Required on every
        // shipment — ECPay rejects empty sender fields.
        'sender_name' => env('ECPAY_SENDER_NAME', '法芮可有限公司'),
        'sender_cellphone' => env('ECPAY_SENDER_CELLPHONE'),
        // Auto-trigger CVS shipment creation on order paid / COD created.
        // Leave unset during first-time testing; flip to true once the
        // first few manual sandbox shipments succeed.
        'logistics_auto' => (bool) env('ECPAY_LOGISTICS_AUTO', false),
    ],

];
