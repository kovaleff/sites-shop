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

    'vk' => [
        'client_id' => env('VK_CLIENT_ID'),
        'client_secret' => env('VK_CLIENT_SECRET'),
        'redirect_uri' => env('VK_REDIRECT_URI', '/api/vk/callback'),
    ],

    'yandex' => [
        'client_id' => env('YA_CLIENT_ID'),
        'client_secret' => env('YA_CLIENT_SECRET'),
        'redirect' => env('YA_REDIRECT_URI', '/api/ya/callback'),
        'allowed_redirects' => env('YA_ALLOWED_REDIRECTS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Robokassa (СБП QR)
    |--------------------------------------------------------------------------
    |
    | Секреты используются только на сервере: подпись счёта считается
    | Паролем #1, подпись callback проверяется Паролем #2. В браузер уходит
    | лишь одноразовая подпись конкретного счёта.
    |
    */

    'robokassa' => [
        'merchant_login' => env('ROBOKASSA_MERCHANT_LOGIN'),
        'password1' => env('ROBOKASSA_PASSWORD1'),
        'password2' => env('ROBOKASSA_PASSWORD2'),

        'test_merchant_login' => env('ROBOKASSA_TEST_MERCHANT_LOGIN'),
        'test_password1' => env('ROBOKASSA_TEST_PASSWORD1'),
        'test_password2' => env('ROBOKASSA_TEST_PASSWORD2'),
        'test_mode' => (bool) env('ROBOKASSA_TEST_MODE', false),

        'result_url' => env('ROBOKASSA_RESULT_URL', '/api/v1/payments/robokassa/result'),
        'success_url' => env('ROBOKASSA_SUCCESS_URL', '/cabinet/sites?payment=success'),
        'fail_url' => env('ROBOKASSA_FAIL_URL', '/cabinet/sites?payment=fail'),

        'script_url' => env(
            'ROBOKASSA_SCRIPT_URL',
            'https://auth.robokassa.ru/merchant/bundle/robokassa-iframe-badge.js',
        ),

        'sno' => env('ROBOKASSA_SNO', 'osn'),
        'tax' => env('ROBOKASSA_TAX', 'none'),
        'payment_object' => env('ROBOKASSA_PAYMENT_OBJECT', 'service'),
    ],

];
