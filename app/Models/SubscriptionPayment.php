<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Database\Factories\SubscriptionPaymentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Filters\Types\WhereIn;

/**
 * Попытка оплаты подписки объявления.
 *
 * Снимок условий (домен, цена, скидка, срок, целевой статус, email, чек)
 * фиксируется при выставлении счёта и не пересчитывается при изменении тарифа.
 */
#[Fillable([
    'site_id',
    'user_id',
    'site_domain',
    'amount',
    'base_price',
    'base_amount',
    'discount_percent',
    'discount_amount',
    'currency',
    'period_months',
    'target_status',
    'receipt_email',
    'receipt_payload',
    'status',
    'paid_at',
    'qr_expires_at',
    'external_id',
    'provider',
    'payment_method',
    'provider_payload',
    'failure_reason',
    'review_reason',
    'is_test',
])]
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
        'discount_percent' => 0,
        'discount_amount' => 0,
        'is_test' => false,
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
            'base_price' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'integer',
            'period_months' => 'integer',
            'paid_at' => 'datetime',
            'qr_expires_at' => 'datetime',
            'receipt_payload' => 'array',
            'provider_payload' => 'array',
            'payment_method' => PaymentMethod::class,
            'provider' => PaymentProvider::class,
            'is_test' => 'boolean',
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

    public function reviews(): HasMany
    {
        return $this->hasMany(SubscriptionPaymentReview::class);
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(SubscriptionPaymentReview::class)->latestOfMany();
    }

    /**
     * Номер счёта для провайдера и админки.
     */
    public function invoiceNumber(): int
    {
        return (int) $this->getKey();
    }

    /**
     * Домен на момент выставления счёта; для старых записей — текущий.
     */
    public function snapshotDomain(): ?string
    {
        return $this->site_domain ?? $this->site?->domain;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function requiresReview(): bool
    {
        return $this->status === PaymentStatus::RequiresReview;
    }

    public function isExpired(): bool
    {
        return $this->status === PaymentStatus::Pending
            && $this->qr_expires_at !== null
            && $this->qr_expires_at->isPast();
    }

    /**
     * Ручная оплата администратором (сохранено для совместимости).
     *
     * Применяет оплату через общий финализатор: продлевает подписку от
     * более поздней даты и выставляет целевой статус счёта.
     */
    public function markAsPaid(): void
    {
        DB::transaction(function (): void {
            $payment = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $site = Site::query()->lockForUpdate()->findOrFail($payment->site_id);

            if ($payment->status === PaymentStatus::Paid) {
                return;
            }

            $canBePaid = $payment->status === PaymentStatus::Pending
                || $payment->status === PaymentStatus::Cancelled
                || $payment->status === PaymentStatus::Failed;

            if (! $canBePaid) {
                throw new DomainException('A payment under review cannot be paid without an admin decision.');
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

            $target = $payment->target_status !== null
                ? (ListingStatus::tryFrom((string) $payment->target_status) ?? $site->status)
                : $site->status;

            $site->applyPaidSubscription($payment->period_months, $target);

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'failure_reason' => null,
                'review_reason' => null,
            ])->save();
        });

        $this->refresh();
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->finishAs(PaymentStatus::Failed, $reason);
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $this->finishAs(PaymentStatus::Cancelled, $reason);
    }

    private function finishAs(PaymentStatus $status, ?string $reason = null): void
    {
        if ($this->status === PaymentStatus::Paid) {
            throw new DomainException('A paid payment cannot be finalized again.');
        }

        if (! $this->status->canTransitionTo($status) && $this->status !== $status) {
            throw new DomainException(
                "Cannot change payment status from {$this->status->value} to {$status->value}."
            );
        }

        $this->status = $status;

        if ($reason !== null) {
            $this->failure_reason = $reason;
        }

        $this->save();
    }
}
