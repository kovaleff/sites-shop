<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Параметры для `Robokassa.pay.startOp({ paymentMethod: 'SBP', … })`.
 *
 * Подпись — не секрет: она привязана к конкретному счёту и сумме.
 */
class RobokassaStartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'merchantLogin' => $this['merchantLogin'],
            'outSum' => $this['outSum'],
            'invId' => $this['invId'],
            'receipt' => $this['receipt'],
            'signature' => $this['signature'],
            'isTest' => $this['isTest'],
            'scriptUrl' => $this['scriptUrl'],
        ];
    }
}
