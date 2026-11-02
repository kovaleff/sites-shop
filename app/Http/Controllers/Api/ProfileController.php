<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\SessionUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Данные профиля текущего пользователя для формы кабинета.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new SessionUserResource($request->user()),
        ]);
    }

    /**
     * Имя, описание и город, которые видны на публичной странице продавца.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return response()->json([
            'user' => new SessionUserResource($user->refresh()),
        ]);
    }
}
