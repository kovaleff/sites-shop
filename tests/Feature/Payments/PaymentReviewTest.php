<?php

namespace Tests\Feature\Payments;

use App\Enums\ListingStatus;
use App\Enums\PaymentReviewDecision;
use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionSetting;
use App\Models\User;
use App\Services\Payments\PaymentReviewService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SubscriptionSetting::query()->updateOrCreate(['id' => 1], [
            'base_price' => 500,
            'periods' => [1, 3, 6, 12],
            'discounts' => [],
            'target_status' => 'active',
            'qr_ttl_minutes' => 15,
            'is_active' => true,
        ]);
        Cache::forget(SubscriptionSetting::CACHE_KEY);
    }

    public function test_accepting_payment_extends_subscription_and_sets_target_status(): void
    {
        $site = Site::factory()->withdrawn()->create(['paid_until' => null]);
        $payment = SubscriptionPayment::factory()->for($site)->requiresReview()->create([
            'user_id' => $site->user_id,
            'period_months' => 3,
            'target_status' => ListingStatus::Active->value,
        ]);

        app(PaymentReviewService::class)->accept($payment, User::factory()->create(), 'Сумма подтверждена вручную.');

        $payment->refresh();
        $site->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(ListingStatus::Active, $site->status);
        $this->assertSame(today()->addMonthsNoOverflow(3)->toDateString(), $site->paid_until->toDateString());
        $this->assertDatabaseHas('subscription_payment_reviews', [
            'subscription_payment_id' => $payment->id,
            'decision' => PaymentReviewDecision::Accepted->value,
        ]);
    }

    public function test_rejecting_payment_marks_failed_without_extension(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $payment = SubscriptionPayment::factory()->for($site)->requiresReview()->create([
            'user_id' => $site->user_id,
        ]);

        app(PaymentReviewService::class)->reject($payment, User::factory()->create(), 'Оплата не подтверждена банком.');

        $payment->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('rejected_by_admin', $payment->failure_reason);
        $this->assertNull($site->fresh()->paid_until);
        $this->assertDatabaseHas('subscription_payment_reviews', [
            'subscription_payment_id' => $payment->id,
            'decision' => PaymentReviewDecision::Rejected->value,
        ]);
    }

    public function test_comment_is_mandatory(): void
    {
        $site = Site::factory()->create();
        $payment = SubscriptionPayment::factory()->for($site)->requiresReview()->create([
            'user_id' => $site->user_id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(PaymentReviewService::class)->accept($payment, User::factory()->create(), 'ok');
    }

    public function test_only_review_payments_can_be_reviewed(): void
    {
        $site = Site::factory()->create();
        $payment = SubscriptionPayment::factory()->for($site)->paid()->create([
            'user_id' => $site->user_id,
        ]);

        $this->expectException(DomainException::class);

        app(PaymentReviewService::class)->accept($payment, User::factory()->create(), 'Повторное решение.');
    }

    public function test_admin_screen_accepts_review_payment(): void
    {
        $admin = User::factory()->create([
            'permissions' => [
                'platform.index' => true,
                'platform.marketplace.payments' => true,
            ],
        ]);

        $site = Site::factory()->create(['paid_until' => null]);
        $payment = SubscriptionPayment::factory()->for($site)->requiresReview()->create([
            'user_id' => $site->user_id,
        ]);

        $this->actingAs($admin)->post(
            route('platform.marketplace.payments.show', [$payment, 'decide']),
            [
                'review' => [
                    'decision' => PaymentReviewDecision::Accepted->value,
                    'comment' => 'Проверено по выписке банка.',
                ],
            ],
        )->assertRedirect();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertNotNull($site->fresh()->paid_until);
    }

    public function test_admin_screen_rejects_short_comment(): void
    {
        $admin = User::factory()->create([
            'permissions' => [
                'platform.index' => true,
                'platform.marketplace.payments' => true,
            ],
        ]);

        $site = Site::factory()->create();
        $payment = SubscriptionPayment::factory()->for($site)->requiresReview()->create([
            'user_id' => $site->user_id,
        ]);

        $this->actingAs($admin)->post(
            route('platform.marketplace.payments.show', [$payment, 'decide']),
            [
                'review' => [
                    'decision' => PaymentReviewDecision::Rejected->value,
                    'comment' => 'no',
                ],
            ],
        )->assertSessionHasErrors('review.comment');

        $this->assertSame(PaymentStatus::RequiresReview, $payment->fresh()->status);
    }
}
