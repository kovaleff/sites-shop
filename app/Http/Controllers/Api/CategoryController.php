<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Справочник тематик для формы объявления.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()->orderBy('name')->get();

        return response()->json([
            'items' => CategoryResource::collection($categories)->resolve($request),
        ]);
    }
}
