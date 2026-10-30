<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuthError;
use App\Exceptions\YandexAuthException;
use App\Http\Controllers\Controller;
use App\Services\SocialAuth\AccountProvisioner;
use App\Services\YandexAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class YandexAuthController extends Controller
{
    /** Срок жизни попытки входа: state действует не более 10 минут (FR-020). */
    private const STATE_TTL_MINUTES = 10;

    public function __construct(
        private readonly YandexAuthService $yandexAuth,
        private readonly AccountProvisioner $provisioner,
    ) {}

    /**
     * Запуск входа: state кладёт в сессию Socialite, метку начала попытки — мы
     * (по ней на колбэке считается срок жизни).
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect('/?auth_error='.AuthError::NotConfigured->value);
        }

        $redirectUri = $this->redirectUri($request);

        // Домен берётся из запроса (Host подконтролен клиенту), поэтому адрес
        // возврата сверяется с явным белым списком до обращения к Яндексу (FR-019).
        if (! $this->isAllowedRedirect($redirectUri)) {
            Log::warning('Yandex OAuth error', [
                'reason' => 'redirect_not_allowed',
                'redirect_uri' => $redirectUri,
            ]);

            return redirect('/?auth_error='.AuthError::NotConfigured->value);
        }

        $request->session()->put('ya_oauth_started_at', now()->timestamp);

        return Socialite::driver('yandex')
            ->redirectUrl($redirectUri)
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->fail($request, AuthError::NotConfigured, 'missing_configuration');
        }

        // 1. Пользователь отказался дать доступ.
        if ($request->filled('error')) {
            return $this->fail($request, AuthError::Cancelled, (string) $request->get('error'));
        }

        // 2. Срок жизни попытки — проверяется до обмена кода, без сети.
        if (! $this->attemptIsFresh($request)) {
            return $this->fail($request, AuthError::State, 'attempt_expired');
        }

        try {
            $profile = Socialite::driver('yandex')
                ->redirectUrl($this->redirectUri($request))
                ->user();
        } catch (InvalidStateException $e) {
            return $this->fail($request, AuthError::State, 'invalid_state', $e);
        } catch (YandexAuthException $e) {
            return $this->fail($request, AuthError::Failed, $e->reason(), $e);
        } catch (Throwable $e) {
            return $this->fail($request, AuthError::Failed, 'exchange_failed', $e);
        }

        try {
            $userProfile = $this->yandexAuth->profileFromSocialite($profile);
            $user = $this->provisioner->provision($userProfile);
        } catch (Throwable $e) {
            return $this->fail($request, AuthError::Failed, 'login_failed', $e);
        }

        Auth::login($user, true);

        // Защита от фиксации сессии: идентификатор до входа не должен пережить вход.
        $request->session()->regenerate();

        $request->session()->forget(['state', 'ya_oauth_started_at']);

        return redirect('/');
    }

    /**
     * Любой неуспех: журнал без секретов, очистка попытки, возврат на главную
     * с кодом auth_error (contracts/spa-auth-errors.md §3.1). Сессия не меняется.
     */
    private function fail(Request $request, AuthError $authError, string $reason, ?Throwable $exception = null): RedirectResponse
    {
        Log::warning('Yandex OAuth error', array_filter([
            'reason' => $reason,
            'auth_error' => $authError->value,
            'exception' => $exception?->getMessage(),
        ]));

        $request->session()->forget(['state', 'ya_oauth_started_at']);

        return redirect('/?auth_error='.$authError->value);
    }

    private function attemptIsFresh(Request $request): bool
    {
        $startedAt = $request->session()->get('ya_oauth_started_at');

        if ($startedAt === null) {
            return false;
        }

        return now()->timestamp - (int) $startedAt <= self::STATE_TTL_MINUTES * 60;
    }

    private function isConfigured(): bool
    {
        return filled(config('services.yandex.client_id'))
            && filled(config('services.yandex.client_secret'))
            && filled(config('services.yandex.redirect'));
    }

    private function isAllowedRedirect(string $redirectUri): bool
    {
        $allowed = array_filter(array_map(
            'trim',
            explode(',', (string) config('services.yandex.allowed_redirects')),
        ));

        return in_array($redirectUri, $allowed, true);
    }

    /**
     * redirect_uri обязан совпадать с зарегистрированным в приложении Яндекса,
     * поэтому собирается из текущего запроса, а не из APP_URL.
     */
    private function redirectUri(Request $request): string
    {
        return $request->getSchemeAndHttpHost().config('services.yandex.redirect');
    }
}
