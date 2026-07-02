<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FahiPay Merchant Credentials (Shop ID)
    |--------------------------------------------------------------------------
    */
    'shop_id' => env('FAHIPAY_SHOP_ID', env('FAHIPAY_MERCHANT_ID', '')),
    'secret_key' => env('FAHIPAY_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    */
    'test_mode' => env('FAHIPAY_TEST_MODE', false),
    'test_base_url' => env('FAHIPAY_TEST_BASE_URL', 'https://test.fahipay.mv/api/merchants'),
    'base_url' => env('FAHIPAY_BASE_URL', 'https://fahipay.mv/api/merchants'),
    'web_url' => env('FAHIPAY_WEB_URL', 'https://fahipay.mv'),
    'test_web_url' => env('FAHIPAY_TEST_WEB_URL', 'https://test.fahipay.mv'),
    'api_key' => env('FAHIPAY_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | URLs (Callback URLs)
    |--------------------------------------------------------------------------
    */
    'return_url' => env('FAHIPAY_RETURN_URL', '/fahipay/callback/success'),
    'cancel_url' => env('FAHIPAY_CANCEL_URL', '/fahipay/callback/cancel'),
    'error_url' => env('FAHIPAY_ERROR_URL', '/fahipay/callback/error'),

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    */
    'timeout' => env('FAHIPAY_TIMEOUT', 30),
    'retry_attempts' => env('FAHIPAY_RETRY_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'prefix' => env('FAHIPAY_TRANSACTION_PREFIX', 'PAY'),
        'unique_id_length' => 12,
        'expire_hours' => 24,
        'expire_without_verification' => env('FAHIPAY_EXPIRE_WITHOUT_VERIFICATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'fahipay',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | These endpoints expose payment CRUD (list/create/show/update/delete).
    | The middleware below is applied to every API route, so it MUST include
    | an authentication guard (e.g. 'auth:sanctum') before enabling the API in
    | production — the default 'api' group does not authenticate requests.
    |
    */
    'api' => [
        'enabled' => false,
        'prefix' => 'api/fahipay',
        'middleware' => ['api', 'auth'],

        // Administrative endpoints (list all payments, update, delete) are
        // destructive / data-exposing. They are disabled by default. Enable
        // them only behind an authenticated middleware stack.
        'admin_enabled' => env('FAHIPAY_API_ADMIN_ENABLED', false),
        'admin_middleware' => ['auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'enabled' => true,
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    'events' => [
        \Fahipay\Gateway\Events\PaymentInitiatedEvent::class => [],
        \Fahipay\Gateway\Events\PaymentCompletedEvent::class => [],
        \Fahipay\Gateway\Events\PaymentFailedEvent::class => [],
        \Fahipay\Gateway\Events\PaymentCancelledEvent::class => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('FAHIPAY_LOGGING', true),
        'channel' => env('FAHIPAY_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'theme' => 'bootstrap',
        'views_namespace' => 'fahipay::',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'allowed_redirect_hosts' => [],
    'allow_unrestricted_callback_urls' => env('FAHIPAY_ALLOW_UNRESTRICTED_CALLBACK_URLS', false),
];
