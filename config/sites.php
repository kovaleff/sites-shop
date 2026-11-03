<?php

return [
    /*
     * Максимум объявлений в активных статусах на одного продавца.
     * 0 — без ограничения.
     */
    'max_active_per_user' => (int) env('SITES_MAX_ACTIVE_PER_USER', 3),

    /*
     * Сколько объявлений пользователь может создать за сутки.
     */
    'creation_per_day' => (int) env('SITES_CREATION_PER_DAY', 5),

    'verification' => [
        'connect_timeout' => (int) env('SITES_VERIFICATION_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('SITES_VERIFICATION_TIMEOUT', 5),

        /*
         * Минимальный возраст домена в днях по данным RDAP.
         * 0 — проверка выключена.
         */
        'min_domain_age_days' => (int) env('SITES_MIN_DOMAIN_AGE_DAYS', 0),
    ],

    /*
     * Учёт просмотров объявлений. Выключается на стендах без аналитики.
     */
    'views' => [
        'enabled' => (bool) env('SITES_VIEWS_ENABLED', true),
    ],

    /*
     * Яндекс SmartCaptcha. Пустые ключи выключают проверку.
     */
    'captcha' => [
        'server_key' => (string) env('SITES_CAPTCHA_SERVER_KEY', ''),
        'site_key' => (string) env('SITES_CAPTCHA_SITE_KEY', ''),
    ],

    /*
     * Google Safe Browsing. Пустой ключ выключает проверку.
     */
    'safe_browsing' => [
        'key' => (string) env('SITES_SAFE_BROWSING_KEY', ''),
    ],
];
