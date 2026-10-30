<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuthError;
use App\Exceptions\VkAuthException;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionUserResource;
use App\Services\SocialAuth\AccountProvisioner;
use App\Services\VkAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class VkAuthController extends Controller
{
    /**
     * PKCE code_verifier фронтенд приносит в cookie: redirect_uri обязан байт в байт
     * совпадать с «Доверенным redirect URL» из кабинета VK ID, поэтому в URL его не положить.
     */
    private const CODE_VERIFIER_COOKIE = 'vk_code_verifier';

    public function __construct(
        private readonly VkAuthService $vkAuth,
        private readonly AccountProvisioner $provisioner,
    ) {}

    /**
     * Веб-колбэк VK ID: обмен кода на сессию (cookie) для SPA.
     * Валидны оба пути: навигация (форма VK) и fetch с Accept: application/json.
     */
    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $this->forgetVerifierCookie();

        // 1. Незаполненные параметры приложения — входа не будет (contracts 3.2).
        if (! $this->isConfigured()) {
            return $this->fail($request, AuthError::VkNotConfigured, 'missing_configuration', 400);
        }

        // 2. Пользователь отказал в доступе (error=access_denied).
        if ($request->filled('error')) {
            return $this->fail($request, AuthError::VkCancelled, (string) $request->get('error'), 400);
        }

        $code = $request->get('code');

        if (! $code) {
            return $this->fail($request, AuthError::VkFailed, 'missing_code', 400);
        }

        $codeVerifier = (string) ($request->get('code_verifier') ?: $request->cookie(self::CODE_VERIFIER_COOKIE, ''));

        try {
            $profile = $this->vkAuth->loginWithCode(
                $code,
                $codeVerifier,
                (string) $request->get('device_id', ''),
                // redirect_uri на обмене кода должен совпадать с тем, что VK видел
                // в authorize, поэтому берём адрес текущего запроса, а не APP_URL.
                $request->url(),
            );

            $user = $this->provisioner->provision($profile);
        } catch (VkAuthException $e) {
            return $this->fail($request, AuthError::VkFailed, $e->reason(), 400, $e);
        } catch (Throwable $e) {
            Log::error('VK OAuth exception', ['message' => $e->getMessage()]);

            return $this->fail($request, AuthError::VkFailed, 'unexpected_error', 500, $e);
        }

        Auth::login($user, true);

        // Защита от фиксации сессии: идентификатор до входа не должен пережить вход.
        $request->session()->regenerate();

        $payload = [
            'authenticated' => true,
            'user' => new SessionUserResource($user),
        ];

        return $request->expectsJson()
            ? response()->json($payload)
            : redirect('/');
    }

    /**
     * Любой неуспех: журнал без секретов, ответ JSON или редирект по типу запроса
     * (contracts/spa-auth-errors.md §3.2). Сессия не меняется.
     */
    private function fail(
        Request $request,
        AuthError $error,
        string $reason,
        int $status,
        ?Throwable $exception = null
    ): JsonResponse|RedirectResponse {
        Log::warning('VK OAuth error', array_filter([
            'reason' => $reason,
            'auth_error' => $error->value,
            'exception' => $exception?->getMessage(),
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'error' => $error->value,
                'message' => $error->message(),
            ], $status);
        }

        return redirect('/?auth_error='.$error->value);
    }

    private function isConfigured(): bool
    {
        return filled(config('services.vk.client_id'))
            && filled(config('services.vk.client_secret'))
            && filled(config('services.vk.redirect_uri'));
    }

    private function forgetVerifierCookie(): void
    {
        cookie()->queue(cookie()->forget(self::CODE_VERIFIER_COOKIE, '/'));
    }
}
