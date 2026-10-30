<?php

use App\Enums\AuthError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // VK ID возвращает код отправкой формы с чужого домена — CSRF-токена в запросе нет.
        $middleware->preventRequestForgery(except: ['api/vk/callback']);

        // PKCE code_verifier фронтенд кладёт в cookie обычным JS (без шифрования Laravel),
        // иначе EncryptCookies выбросит его как нерасшифровываемый.
        $middleware->encryptCookies(except: ['vk_code_verifier']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Лимит входа (throttle:10,1). JSON-запрос → 429, навигация → редирект.
        // Сессия при этом не меняется (contracts/spa-auth-errors.md §3.3).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $error = AuthError::TooManyRequests;

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $error->value,
                    'message' => $error->message(),
                ], 429);
            }

            return redirect('/?auth_error='.$error->value);
        });
    })->create();
