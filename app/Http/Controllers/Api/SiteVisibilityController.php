<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteVisibilityController extends Controller
{
    /**
     * Скрыть проданное объявление из списка кабинета (FR-014a).
     */
    public function store(Request $request, int $site): JsonResponse
    {
        $model = $request->user()->sites()->findOrFail($site);

        try {
            $model->hideFromCabinet();
        } catch (DomainException) {
            throw ValidationException::withMessages([
                'status' => 'Скрыть можно только проданное объявление.',
            ]);
        }

        return (new SiteResource($model->load('categories')))->response();
    }

    /**
     * Вернуть скрытое объявление в список.
     */
    public function destroy(Request $request, int $site): JsonResponse
    {
        $model = $request->user()->sites()->findOrFail($site);

        $model->restoreFromCabinet();

        return (new SiteResource($model->load('categories')))->response();
    }
}
