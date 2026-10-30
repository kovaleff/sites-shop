<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MySiteController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SiteStatusController;
use App\Http\Controllers\Api\SiteVisibilityController;
use App\Http\Controllers\Api\UnreadMessageController;
use App\Http\Controllers\Api\VkAuthController;
use App\Http\Controllers\Api\YandexAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
})->name('catalog');

Route::get('/how-it-works', function () {
    return view('app');
})->name('how-it-works');

Route::get('/pricing', function () {
    return view('app');
})->name('pricing');

// Оболочка SPA для личного кабинета: данные приходят только из API,
// поэтому вход здесь не проверяется — этим занимается маршрутизатор SPA.
Route::get('/cabinet/{any?}', function () {
    return view('app');
})->where('any', '.*')->name('cabinet');

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
    Route::get('/catalog', [CatalogController::class, 'index'])->name('api.v1.catalog');
    Route::get('/catalog/zones', [CatalogController::class, 'zones'])->name('api.v1.catalog.zones');
    Route::get('/categories', [CategoryController::class, 'index'])->name('api.v1.categories');

    Route::middleware('auth')->group(function () {
        Route::get('/my/sites', [MySiteController::class, 'index'])->name('api.v1.my-sites.index');
        Route::post('/my/sites', [MySiteController::class, 'store'])->name('api.v1.my-sites.store');
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
    });
});
