<?php

namespace App\Providers;

use App\Services\Domain\DnsDomainResolver;
use App\Services\Domain\DomainResolver;
use App\Services\Domain\GoogleSafeBrowsingChecker;
use App\Services\Domain\ReputationChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Yandex\YandexExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DomainResolver::class, DnsDomainResolver::class);
        $this->app->bind(ReputationChecker::class, GoogleSafeBrowsingChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, YandexExtendSocialite::class);

        RateLimiter::for('create-site', function (Request $request): Limit {
            return Limit::perDay((int) config('sites.creation_per_day'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Слишком много новых объявлений за сутки. Попробуйте позже.',
                ], 429));
        });

        // Выставление счёта на продление подписки (FR-036).
        RateLimiter::for('create-payment', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by('payment-user:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
                ->response(fn () => response()->json([
                    'message' => 'Слишком много попыток оплаты. Подождите минуту и повторите.',
                ], 429));
        });

        // Проверка статуса платежа и повторное открытие QR.
        RateLimiter::for('payment-status', function (Request $request): Limit {
            return Limit::perMinute(60)
                ->by('payment-status:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
                ->response(fn () => response()->json([
                    'message' => 'Слишком много запросов статуса оплаты. Попробуйте позже.',
                ], 429));
        });

        // Публичный ResultURL Robokassa — ограничение по IP.
        RateLimiter::for('payment-callback', function (Request $request): Limit {
            return Limit::perMinute(120)->by('payment-callback:'.$request->ip());
        });
    }
}
