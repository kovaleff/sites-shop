<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Requests\UpdateSavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use App\Services\Catalog\CatalogFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $searches = $request->user()->savedSearches()
            ->latest('created_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => SavedSearchResource::collection($searches)->resolve($request),
        ]);
    }

    public function store(StoreSavedSearchRequest $request, CatalogFilter $catalogFilter): JsonResponse
    {
        $data = $request->validated();
        $filters = $catalogFilter->normalize($data['filters']);

        $search = $request->user()->savedSearches()->create([
            'name' => $data['name'] ?? null,
            'frequency' => $data['frequency'],
            'filters' => $filters,
            // Письмо не должно приходить сразу за уже существующие лоты.
            'last_notified_at' => now(),
        ]);

        return (new SavedSearchResource($search))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSavedSearchRequest $request, int $savedSearch): SavedSearchResource
    {
        $search = $request->user()->savedSearches()->findOrFail($savedSearch);
        $search->fill($request->validated())->save();

        return new SavedSearchResource($search);
    }

    public function destroy(Request $request, int $savedSearch): JsonResponse
    {
        $request->user()->savedSearches()->findOrFail($savedSearch)->delete();

        return response()->json(['deleted' => true]);
    }
}
