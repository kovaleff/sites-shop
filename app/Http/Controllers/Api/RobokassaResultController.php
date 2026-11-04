<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentFinalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RobokassaResultController extends Controller
{
    public function __construct(private readonly PaymentFinalizer $finalizer) {}

    /**
     * Серверное уведомление Robokassa (FR-015–FR-019).
     *
     * Ответ — `text/plain`: `OK{InvId}` при принятом уведомлении,
     * `ERROR` при неверной подписи или запрещённом тестовом callback.
     */
    public function __invoke(Request $request): Response
    {
        $result = $this->finalizer->handleCallback($request->all());

        return response($result['body'], $result['status'])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
