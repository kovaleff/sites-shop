<?php

namespace Tests\Feature\Models;

use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_payment_extends_only_its_own_site(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $otherSite = Site::factory()->for($site->owner, 'owner')->create(['paid_until' => today()->addDays(3)]);

        $payment = SubscriptionPayment::factory()->for($site)->create();
        $payment->markAsPaid();

        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $site->fresh()->paid_until->toDateString());
        $this->assertSame(
            today()->addDays(3)->toDateString(),
            $otherSite->fresh()->paid_until->toDateString(),
        );
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_expired_or_missing_subscription_starts_from_today(): void
    {
        $expiredSite = Site::factory()->create(['paid_until' => today()->subMonth()]);
        $expiredPayment = SubscriptionPayment::factory()->for($expiredSite)->create();
        $expiredPayment->markAsPaid();

        $newSite = Site::factory()->create(['paid_until' => null]);
        $newPayment = SubscriptionPayment::factory()->for($newSite)->create();
        $newPayment->markAsPaid();

        $expected = today()->addMonthNoOverflow()->toDateString();

        $this->assertSame($expected, $expiredSite->fresh()->paid_until->toDateString());
        $this->assertSame($expected, $newSite->fresh()->paid_until->toDateString());
    }

    public function test_future_subscription_is_extended_from_its_current_date(): void
    {
        $future = today()->addDays(10);
        $site = Site::factory()->create(['paid_until' => $future]);

        $payment = SubscriptionPayment::factory()->for($site)->create(['period_months' => 2]);
        $payment->markAsPaid();

        $this->assertSame(
            $future->addMonthsNoOverflow(2)->toDateString(),
            $site->fresh()->paid_until->toDateString(),
        );
    }

    public function test_mark_as_paid_is_idempotent(): void
    {
        $site = Site::factory()->create(['paid_until' => today()]);
        $payment = SubscriptionPayment::factory()->for($site)->create();

        $payment->markAsPaid();
        $afterFirstPayment = $site->fresh()->paid_until;

        $payment->markAsPaid();

        $this->assertSame($afterFirstPayment->toDateString(), $site->fresh()->paid_until->toDateString());
    }

    public function test_failed_and_cancelled_payments_do_not_change_the_date(): void
    {
        $site = Site::factory()->create(['paid_until' => today()->addDays(5)]);

        $failed = SubscriptionPayment::factory()->for($site)->create();
        $failed->markAsFailed();

        $cancelled = SubscriptionPayment::factory()->for($site)->create();
        $cancelled->markAsCancelled();

        $this->assertSame(PaymentStatus::Failed, $failed->fresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(
            today()->addDays(5)->toDateString(),
            $site->fresh()->paid_until->toDateString(),
        );
    }

    public function test_withdrawn_and_sold_sites_reject_payment(): void
    {
        foreach ([
            Site::factory()->withdrawn()->create(),
            Site::factory()->sold()->create(),
        ] as $site) {
            $payment = SubscriptionPayment::factory()->for($site)->create();

            try {
                $payment->markAsPaid();
                $this->fail('A non-payable site must reject payment.');
            } catch (DomainException) {
                $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
            }
        }
    }

    public function test_invalid_amount_and_period_are_rejected(): void
    {
        $site = Site::factory()->create();

        $zeroAmount = SubscriptionPayment::factory()->for($site)->create(['amount' => 0]);
        $zeroPeriod = SubscriptionPayment::factory()->for($site)->create(['period_months' => 0]);

        foreach ([$zeroAmount, $zeroPeriod] as $payment) {
            try {
                $payment->markAsPaid();
                $this->fail('An invalid payment must be rejected.');
            } catch (DomainException) {
                $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
            }
        }

        $this->assertNull($site->fresh()->paid_until);
    }

    public function test_final_payment_cannot_be_marked_failed(): void
    {
        $payment = SubscriptionPayment::factory()->paid()->create();

        $this->expectException(DomainException::class);

        $payment->markAsFailed();
    }

    public function test_payment_relations_are_correct(): void
    {
        $site = Site::factory()->create();
        $payment = SubscriptionPayment::factory()->for($site)->create();

        $this->assertTrue($payment->site->is($site));
        $this->assertTrue($payment->payer->is($site->owner));
    }
}
