<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeSiteStatusRequest;
use App\Http\Resources\SiteResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SiteStatusController extends Controller
{
    /**
     * Смена статуса по допустимым переходам (FR-013, FR-014).
     */
    public function update(ChangeSiteStatusRequest $request, int $site): JsonResponse
    {
        $model = $request->user()->sites()->findOrFail($site);

        try {
            $model->changeStatus(ListingStatus::from($request->validated()['status']));
        } catch (DomainException) {
            throw ValidationException::withMessages([
                'status' => 'Нельзя перейти в этот статус из текущего.',
            ]);
        }

        return (new SiteResource($model->load('categories')))
            ->response();
    }
}
