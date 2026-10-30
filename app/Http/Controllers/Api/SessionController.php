<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    /**
     * Текущее состояние сессии. Всегда 200: SPA инициализируется
     * без обработки ожидаемой ошибки (contracts/auth-api.yaml).
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => $user !== null,
            'user' => $user !== null ? new SessionUserResource($user) : null,
        ]);
    }

    /**
     * Выход: сессия уничтожается, CSRF-токен регенерируется.
     * Свежий токен нужен SPA для последующих запросов (spa-auth-errors.md §3.4).
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'authenticated' => false,
            'csrfToken' => $request->session()->token(),
        ]);
    }
}
