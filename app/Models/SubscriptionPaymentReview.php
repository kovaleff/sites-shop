<?php

namespace App\Models;

use App\Enums\PaymentReviewDecision;
use Database\Factories\SubscriptionPaymentReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Аудит ручного решения администратора по платежу «Требует проверки».
 */
#[Fillable(['admin_id', 'decision', 'comment'])]
class SubscriptionPaymentReview extends Model
{
    /** @use HasFactory<SubscriptionPaymentReviewFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => PaymentReviewDecision::class,
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
