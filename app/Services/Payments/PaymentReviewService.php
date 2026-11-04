<?php

namespace App\Services\Payments;

use App\Enums\PaymentReviewDecision;
use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Ручное решение по платежу «Требует проверки» (FR-041).
 */
class PaymentReviewService
{
    public function __construct(private readonly PaymentFinalizer $finalizer) {}

    public const COMMENT_MIN = 5;

    public const COMMENT_MAX = 2000;

    public function accept(SubscriptionPayment $payment, User $admin, string $comment): void
    {
        $this->review($payment, $admin, PaymentReviewDecision::Accepted, $comment);
    }

    public function reject(SubscriptionPayment $payment, User $admin, string $comment): void
    {
        $this->review($payment, $admin, PaymentReviewDecision::Rejected, $comment);
    }

    private function review(
        SubscriptionPayment $payment,
        User $admin,
        PaymentReviewDecision $decision,
        string $comment,
    ): void {
        $comment = trim($comment);
        $length = mb_strlen($comment);

        if ($length < self::COMMENT_MIN || $length > self::COMMENT_MAX) {
            throw new InvalidArgumentException(
                'Комментарий должен содержать от '.self::COMMENT_MIN.' до '.self::COMMENT_MAX.' символов.',
            );
        }

        DB::transaction(function () use ($payment, $admin, $decision, $comment): void {
            /** @var SubscriptionPayment $locked */
            $locked = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status !== PaymentStatus::RequiresReview) {
                throw new DomainException('Решение можно принять только по платежу, требующему проверки.');
            }

            if ($decision === PaymentReviewDecision::Accepted) {
                /** @var Site $site */
                $site = Site::query()->lockForUpdate()->findOrFail($locked->site_id);
                $this->finalizer->applyPaid($locked, $site, (array) ($locked->provider_payload ?? []));
            } else {
                $locked->forceFill([
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => 'rejected_by_admin',
                ])->save();
            }

            $locked->reviews()->create([
                'admin_id' => $admin->getKey(),
                'decision' => $decision,
                'comment' => $comment,
            ]);

            Log::info('subscription_payment.reviewed', [
                'inv_id' => $locked->getKey(),
                'site_id' => $locked->site_id,
                'admin_id' => $admin->getKey(),
                'decision' => $decision->value,
            ]);
        });

        $payment->refresh();
    }
}
