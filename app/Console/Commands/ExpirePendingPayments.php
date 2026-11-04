<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentExpiryService;
use Illuminate\Console\Command;

class ExpirePendingPayments extends Command
{
    /**
     * @var string
     */
    protected $signature = 'payments:expire-pending';

    /**
     * @var string
     */
    protected $description = 'Отменяет попытки оплаты с истёкшим сроком действия QR-кода';

    public function handle(PaymentExpiryService $expiry): int
    {
        $count = $expiry->expirePending();

        $this->info("Отменено попыток: {$count}");

        return self::SUCCESS;
    }
}
