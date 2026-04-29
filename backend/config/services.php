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
        // Messaging channel secret — used to verify X-Line-Signature on webhook
        // requests (NOT the Login channel secret above).
        'messaging_channel_secret' => env('LINE_MESSAGING_CHANNEL_SECRET'),
        // OA basic id (e.g. @123abcd) — used to render the QR / friend-add URL
        // on the order-complete page so the customer can add the OA before binding.
        'oa_basic_id' => env('LINE_OA_BASIC_ID'),
    ],

    'discord' => [
        'compliance_webhook' => env('DISCORD_COMPLIANCE_WEBHOOK'),
        'orders_webhook' => env('DISCORD_ORDERS_WEBHOOK'),
        'ads_webhook' => env('DISCORD_ADS_WEBHOOK'),
        // Strategy channel — for Claude-generated analysis. Separate from
        // ads_webhook (which carries raw daily numbers) so the two streams
        // don't noise each other. Falls back to ads_webhook if unset.
        'ads_strategy_webhook' => env('DISCORD_ADS_STRATEGY_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Ads API (read-only)
    |--------------------------------------------------------------------------
    | Populated via OAuth Playground once, refresh_token is long-lived.
    | See /Users/chris/.claude/notes/... for setup runbook.
    */
    'google_ads' => [
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        // Paid account being queried (10-digit, no dashes)
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
        // MCC account that manages it (10-digit, no dashes). Required when
        // paid account is a child of a manager.
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IndexNow (Bing / Yandex)
    |--------------------------------------------------------------------------
    | Public ping protocol — tells search engines to re-crawl specific URLs.
    | Key is a public verification token (not a secret). The matching .txt
    | file lives at frontend/public/{key}.txt — both must match.
    | Google ignores IndexNow; this is for Bing + Yandex.
    */
    'indexnow' => [
        'key' => env('INDEXNOW_KEY', '5ca459d31d8cd8d090592abbd45056d8'),
        'host' => env('INDEXNOW_HOST', 'pandora.js-store.com.tw'),
        'enabled' => (bool) env('INDEXNOW_ENABLED', true),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'https://pandora.js-store.com.tw'),
        'revalidate_secret' => env('REVALIDATE_SECRET'),
    ],

    'cloudflare' => [
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
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

    /*
    |--------------------------------------------------------------------------
    | Pandora Core Identity Service (ADR-001)
    |--------------------------------------------------------------------------
    | Shadow-mirror writes to集團 identity service。dev 預設 disabled，
    | staging / prod 等 platform skeleton 部署完才打開。
    */
    'pandora_core' => [
        'mirror_enabled' => (bool) env('IDENTITY_MIRROR_ENABLED', false),
        'base_url' => env('PANDORA_CORE_BASE_URL'),
        'internal_secret' => env('PANDORA_CORE_INTERNAL_SECRET'),

        // Phase 2 (ADR-007 / pandora-js-store#11)：platform → 母艦 webhook receiver
        // 簽章 secret，與 platform 端 IDENTITY_CONSUMER_PANDORA_JS_STORE_SECRET 一致
        'webhook_secret' => env('PANDORA_CORE_WEBHOOK_SECRET'),
        'webhook_window_seconds' => (int) env('PANDORA_CORE_WEBHOOK_WINDOW_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pandora Conversion Service (py-service, ADR-003)
    |--------------------------------------------------------------------------
    | Internal HMAC secret for signed inbound queries from py-service's
    | `HttpMothershipOrderClient` (loyalist rule needs 母艦 order summary).
    | Empty/missing → endpoint returns 401 (fail closed).
    */
    'pandora_conversion' => [
        'internal_secret' => env('PANDORA_CONVERSION_INTERNAL_SECRET'),
        'window_seconds' => (int) env('PANDORA_CONVERSION_WINDOW_SECONDS', 300),

        // ADR-008 §2.3 — outbound event push to py-service `conversion/`.
        // When base_url is empty we noop (log only) so local/dev order flow
        // never breaks just because py-service isn't running. App ID lets
        // py-service tell which source App fired the event (mothership = fairy_pandora).
        'base_url' => env('PANDORA_CONVERSION_BASE_URL'),
        'app_id' => env('PANDORA_CONVERSION_APP_ID', 'fairy_pandora'),
        'push_timeout_seconds' => (int) env('PANDORA_CONVERSION_PUSH_TIMEOUT', 5),
    ],

    // ADR-009 Phase B — outbound gamification events from 母艦 to py-service +
    // inbound gamification webhook (level_up / achievement_awarded /
    // outfit_unlocked) from py-service. Empty values = noop (safe default).
    'pandora_gamification' => [
        // Outbound publisher
        'base_url' => env('PANDORA_GAMIFICATION_BASE_URL', env('PANDORA_CONVERSION_BASE_URL')),
        'shared_secret' => env('PANDORA_GAMIFICATION_SHARED_SECRET', env('PANDORA_CONVERSION_INTERNAL_SECRET')),
        'timeout' => (int) env('PANDORA_GAMIFICATION_TIMEOUT', 5),
        // Inbound webhook receiver (independent secret; py-service consumer
        // env GAMIFICATION_CONSUMER_JEROSSE_SECRET points here).
        'webhook_secret' => env('PANDORA_GAMIFICATION_WEBHOOK_SECRET'),
        'webhook_window_seconds' => (int) env('PANDORA_GAMIFICATION_WEBHOOK_WINDOW_SECONDS', 300),
    ],

];
