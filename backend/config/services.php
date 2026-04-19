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
        'redirect' => env('GOOGLE_REDIRECT_URI', 'https://pandora.js-store.com.tw/auth/google/callback'),
    ],

    'line' => [
        'client_id' => env('LINE_CHANNEL_ID'),
        'client_secret' => env('LINE_CHANNEL_SECRET'),
        'redirect' => env('LINE_REDIRECT_URI', 'https://pandora.js-store.com.tw/api/auth/line/callback'),
        // LINE Messaging API channel access token — separate from the Login channel above.
        // Required for pushing messages (e.g. abandoned-cart reminders) to a userId.
        // Login + Messaging channels must be in the same Provider for the userId to match.
        'messaging_access_token' => env('LINE_MESSAGING_ACCESS_TOKEN'),
    ],

    'discord' => [
        'compliance_webhook' => env('DISCORD_COMPLIANCE_WEBHOOK'),
        'orders_webhook'     => env('DISCORD_ORDERS_WEBHOOK'),
        'ads_webhook'        => env('DISCORD_ADS_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Ads API (read-only)
    |--------------------------------------------------------------------------
    | Populated via OAuth Playground once, refresh_token is long-lived.
    | See /Users/chris/.claude/notes/... for setup runbook.
    */
    'google_ads' => [
        'developer_token'    => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'client_id'          => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret'      => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token'      => env('GOOGLE_ADS_REFRESH_TOKEN'),
        // Paid account being queried (10-digit, no dashes)
        'customer_id'        => env('GOOGLE_ADS_CUSTOMER_ID'),
        // MCC account that manages it (10-digit, no dashes). Required when
        // paid account is a child of a manager.
        'login_customer_id'  => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
    ],

    'ecpay' => [
        // Payment (金流) credentials
        'merchant_id' => env('ECPAY_MERCHANT_ID'),
        'hash_key' => env('ECPAY_HASH_KEY'),
        'hash_iv' => env('ECPAY_HASH_IV'),
        'mode' => env('ECPAY_MODE', 'sandbox'),
        'frontend_url' => env('FRONTEND_URL', 'https://pandora.js-store.com.tw'),

        // Logistics (物流) credentials — on some ECPay accounts logistics
        // has its own MerchantID/HashKey/HashIV (separate application).
        // If not set, fall back to payment creds (works when the same
        // account covers both products).
        'logistics_merchant_id' => env('ECPAY_LOGISTICS_MERCHANT_ID', env('ECPAY_MERCHANT_ID')),
        'logistics_hash_key' => env('ECPAY_LOGISTICS_HASH_KEY', env('ECPAY_HASH_KEY')),
        'logistics_hash_iv' => env('ECPAY_LOGISTICS_HASH_IV', env('ECPAY_HASH_IV')),

        // Sender info for CVS logistics (Express/Create). Required on every
        // shipment — ECPay rejects empty sender fields.
        'sender_name' => env('ECPAY_SENDER_NAME', '法芮可有限公司'),
        'sender_cellphone' => env('ECPAY_SENDER_CELLPHONE'),
        // Auto-trigger CVS shipment creation on order paid / COD created.
        'logistics_auto' => (bool) env('ECPAY_LOGISTICS_AUTO', false),
    ],

];
