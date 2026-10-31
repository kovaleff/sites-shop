<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnreadMessageController extends Controller
{
    /**
     * Счётчик непрочитанных для бейджа в шапке (FR-032).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadMessagesCount(),
        ]);
    }
}
