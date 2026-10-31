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
    }
}
