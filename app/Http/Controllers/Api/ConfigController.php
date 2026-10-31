<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Публичные настройки SPA: какие проверки включены на сервере.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'captcha' => [
                'enabled' => (string) config('sites.captcha.site_key') !== ''
                    && (string) config('sites.captcha.server_key') !== '',
                'siteKey' => (string) config('sites.captcha.site_key'),
            ],
        ]);
    }
}
