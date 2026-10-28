<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\SubscriptionPaymentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Filters\Types\WhereIn;

#[Fillable([])]
class SubscriptionPayment extends Model
{
    use Filterable;

    /** @use HasFactory<SubscriptionPaymentFactory> */
    use HasFactory;

    /**
     * @var array<string, class-string>
     */
    protected $allowedFilters = [
        'status' => WhereIn::class,
        'paid_at' => WhereDateStartEnd::class,
    ];

    /**
     * @var array<int, string>
     */
    protected $allowedSorts = [
        'created_at',
        'paid_at',
        'amount',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'currency' => 'RUB',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function markAsPaid(): void
    {
        DB::transaction(function (): void {
            $payment = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $site = Site::query()->lockForUpdate()->findOrFail($payment->site_id);

            if ($payment->status === PaymentStatus::Paid) {
                return;
            }

            if ($payment->status->isFinal()) {
                throw new DomainException('A failed or cancelled payment cannot be paid.');
            }

            if (! $site->acceptsPayments()) {
                throw new DomainException('The site does not accept payments in its current status.');
            }

            if ((float) $payment->amount <= 0) {
                throw new DomainException('The payment amount must be greater than zero.');
            }

            if ($payment->period_months < 1) {
                throw new DomainException('The payment period must be at least one month.');
            }

            $site->extendSubscription($payment->period_months);

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
            ])->save();
        });

        $this->refresh();
    }

    public function markAsFailed(): void
    {
        $this->finishAs(PaymentStatus::Failed);
    }

    public function markAsCancelled(): void
    {
        $this->finishAs(PaymentStatus::Cancelled);
    }

    private function finishAs(PaymentStatus $status): void
    {
        if ($this->status !== PaymentStatus::Pending) {
            throw new DomainException('Only a pending payment can be finalized.');
        }

        $this->status = $status;
        $this->save();
    }
}
