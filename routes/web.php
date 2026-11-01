<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MySiteController;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SiteReportController;
use App\Http\Controllers\Api\SiteStatusController;
use App\Http\Controllers\Api\SiteVisibilityController;
use App\Http\Controllers\Api\UnreadMessageController;
use App\Http\Controllers\Api\VkAuthController;
use App\Http\Controllers\Api\YandexAuthController;
use App\Http\Controllers\ListingPageController;
use App\Http\Controllers\NotificationUnsubscribeController;
use App\Http\Controllers\SitemapController;
use App\Support\Seo\SeoMeta;
use Illuminate\Support\Facades\Route;

// Публичные страницы: метатеги отдаются сервером, SPA гидрирует разметку.
Route::get('/', function () {
    return view('app', ['meta' => SeoMeta::forCatalog()->toArray()]);
})->name('catalog');

Route::get('/how-it-works', function () {
    return view('app', [
        'meta' => SeoMeta::forPage(
            'Как это работает',
            'Как купить или продать готовый сайт на Sites-shop: подтверждение владения доменом, '
                .'безопасная сделка и передача домена.',
            '/how-it-works',
        )->toArray(),
    ]);
})->name('how-it-works');

Route::get('/pricing', function () {
    return view('app', [
        'meta' => SeoMeta::forPage(
            'Тарифы',
            'Стоимость размещения объявления о продаже сайта на Sites-shop: подписка для продавцов '
                .'и бесплатный доступ к каталогу для покупателей.',
            '/pricing',
        )->toArray(),
    ]);
})->name('pricing');

// Страница лота: серверный HTML с метатегами, Open Graph и JSON-LD.
Route::get('/site/{slug}', ListingPageController::class)
    ->where('slug', '[0-9]+(-[A-Za-z0-9-]+)?')
    ->name('listing');

// Карта сайта: маршрут вместо статического файла, чтобы список лотов был актуален.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// robots.txt отдаётся маршрутом, чтобы Sitemap указывал на текущий домен.
Route::get('/robots.txt', function () {
    $lines = [
        'User-agent: *',
        'Disallow: /cabinet/',
        'Disallow: /admin/',
        'Disallow: /api/',
        'Disallow: /up',
        '',
        'Sitemap: '.url('/sitemap.xml'),
    ];

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

// Оболочка SPA для личного кабинета: данные приходят только из API,
// поэтому вход здесь не проверяется — этим занимается маршрутизатор SPA.
// Страница закрыта от индексации: содержимое зависит от пользователя.
Route::get('/cabinet/{any?}', function () {
    return view('app', [
        'meta' => (new SeoMeta(
            title: 'Личный кабинет — '.SeoMeta::SITE_NAME,
            description: 'Управление объявлениями, подпиской и сообщениями.',
            canonical: url('/cabinet'),
            indexable: false,
        ))->toArray(),
    ]);
})->where('any', '.*')->name('cabinet');

// Отключение одного типа писем по ссылке из письма: подпись вместо входа в аккаунт.
Route::get('/notifications/unsubscribe/{type}/{user}', NotificationUnsubscribeController::class)
    ->middleware('signed')
    ->name('notifications.unsubscribe');

// Веб-колбэк VK ID. VK возвращает код отправкой формы с чужого домена,
// поэтому маршрут живёт в web-группе: нужна сессия для Auth::login().
// CSRF для него отключён в bootstrap/app.php.
Route::any('/api/vk/callback', [VkAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('api.vk.callback');

// Вход через Яндекс ID: серверный OAuth-поток, state хранится в сессии.
Route::get('/api/ya/redirect', [YandexAuthController::class, 'redirect'])
    ->middleware('throttle:10,1')
    ->name('api.ya.redirect');
Route::get('/api/ya/callback', [YandexAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('api.ya.callback');

// Сессионные маршруты SPA.
Route::get('/api/v1/user', [SessionController::class, 'user'])->name('api.v1.user');
Route::post('/api/v1/auth/logout', [SessionController::class, 'logout'])->name('api.v1.auth.logout');

// JSON-API кабинета и публичного каталога (contracts/cabinet-api.yaml).
Route::prefix('api/v1')->group(function () {
    Route::get('/config', ConfigController::class)->name('api.v1.config');

    Route::get('/catalog', [CatalogController::class, 'index'])->name('api.v1.catalog');
    Route::get('/catalog/zones', [CatalogController::class, 'zones'])->name('api.v1.catalog.zones');
    Route::get('/categories', [CategoryController::class, 'index'])->name('api.v1.categories');
    Route::get('/listings/{slug}', [ListingController::class, 'show'])
        ->where('slug', '[0-9]+(-[A-Za-z0-9-]+)?')
        ->name('api.v1.listings.show');

    Route::middleware('auth')->group(function () {
        Route::get('/my/sites', [MySiteController::class, 'index'])->name('api.v1.my-sites.index');
        Route::post('/my/sites', [MySiteController::class, 'store'])
            ->middleware('throttle:create-site')
            ->name('api.v1.my-sites.store');
        Route::get('/my/sites/{site}', [MySiteController::class, 'show'])->name('api.v1.my-sites.show');
        Route::patch('/my/sites/{site}', [MySiteController::class, 'update'])->name('api.v1.my-sites.update');
        Route::patch('/my/sites/{site}/status', [SiteStatusController::class, 'update'])->name('api.v1.my-sites.status');
        Route::post('/my/sites/{site}/hide', [SiteVisibilityController::class, 'store'])->name('api.v1.my-sites.hide');
        Route::delete('/my/sites/{site}/hide', [SiteVisibilityController::class, 'destroy'])->name('api.v1.my-sites.unhide');

        Route::get('/conversations', [ConversationController::class, 'index'])->name('api.v1.conversations.index');
        Route::post('/conversations', [ConversationController::class, 'store'])->name('api.v1.conversations.store');
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('api.v1.conversations.show');
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('api.v1.conversations.messages.store');
        Route::get('/messages/unread', [UnreadMessageController::class, 'index'])->name('api.v1.messages.unread');

        Route::get('/notification-settings', [NotificationSettingsController::class, 'index'])
            ->name('api.v1.notification-settings.index');
        Route::patch('/notification-settings', [NotificationSettingsController::class, 'update'])
            ->name('api.v1.notification-settings.update');

        Route::post('/sites/{site}/reports', [SiteReportController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('api.v1.sites.reports.store');
    });
});
