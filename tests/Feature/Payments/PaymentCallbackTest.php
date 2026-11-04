<?php

namespace Tests\Feature\Payments;

use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionSetting;
use App\Models\User;
use App\Notifications\PaymentRequiresReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.robokassa.merchant_login' => 'test-shop',
            'services.robokassa.password1' => 'password-1',
            'services.robokassa.password2' => 'password-2',
            'services.robokassa.test_mode' => false,
            'services.robokassa.test_password1' => 'test-password-1',
            'services.robokassa.test_password2' => 'test-password-2',
        ]);

        SubscriptionSetting::query()->updateOrCreate(['id' => 1], [
            'base_price' => 500,
            'periods' => [1, 3, 6, 12],
            'discounts' => [3 => 10, 6 => 15, 12 => 25],
            'target_status' => 'active',
            'qr_ttl_minutes' => 15,
            'is_active' => true,
        ]);
        Cache::forget(SubscriptionSetting::CACHE_KEY);
    }

    public function test_valid_callback_pays_invoice_and_extends_subscription(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $payment = $this->payment($site, ['amount' => 1350, 'period_months' => 3]);

        $this->sendCallback($payment, '1350.000000')
            ->assertOk()
            ->assertSee('OK'.$payment->id);

        $payment->refresh();
        $site->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(ListingStatus::Active, $site->status);
        $this->assertSame(today()->addMonthsNoOverflow(3)->toDateString(), $site->paid_until->toDateString());
    }

    public function test_invalid_signature_returns_error_and_changes_nothing(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $payment = $this->payment($site);

        $this->post(route('api.v1.payments.robokassa.result'), [
            'OutSum' => '500.000000',
            'InvId' => (string) $payment->id,
            'SignatureValue' => 'deadbeef',
            'PaymentMethod' => 'SBP',
        ])->assertStatus(400)->assertSee('ERROR');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertNull($site->fresh()->paid_until);
    }

    public function test_amount_mismatch_requires_review_without_extension(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $payment = $this->payment($site, ['amount' => 500]);

        $this->sendCallback($payment, '1.000000')
            ->assertOk()
            ->assertSee('OK'.$payment->id);

        $payment->refresh();

        $this->assertSame(PaymentStatus::RequiresReview, $payment->status);
        $this->assertSame('amount_mismatch', $payment->review_reason);
        $this->assertNotNull($payment->paid_at);
        $this->assertNull($site->fresh()->paid_until);
    }

    public function test_currency_mismatch_requires_review(): void
    {
        $site = Site::factory()->create();
        $payment = $this->payment($site);

        $this->sendCallback($payment, '500.000000', ['IncCurrLabel' => 'USD'])->assertOk();

        $this->assertSame('currency_mismatch', $payment->fresh()->review_reason);
    }

    public function test_non_sbp_method_requires_review(): void
    {
        $site = Site::factory()->create();
        $payment = $this->payment($site);

        $this->sendCallback($payment, '500.000000', ['PaymentMethod' => 'Card'])->assertOk();

        $this->assertSame('method_mismatch', $payment->fresh()->review_reason);
    }

    public function test_duplicate_callback_does_not_extend_twice(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $payment = $this->payment($site, ['amount' => 500]);

        $this->sendCallback($payment, '500.000000')->assertOk();
        $afterFirst = $site->fresh()->paid_until;

        $this->sendCallback($payment, '500.000000')->assertOk();

        $this->assertSame($afterFirst->toDateString(), $site->fresh()->paid_until->toDateString());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_late_callback_applies_when_site_still_accepts_payment(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $payment = $this->payment($site, [
            'status' => PaymentStatus::Cancelled,
            'failure_reason' => 'expired',
        ]);

        $this->sendCallback($payment, '500.000000')->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertNotNull($site->fresh()->paid_until);
    }

    public function test_late_callback_for_sold_site_requires_review(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $payment = $this->payment($site, ['status' => PaymentStatus::Cancelled]);

        $site->forceFill(['status' => ListingStatus::Sold, 'sold_at' => now()])->save();

        $this->sendCallback($payment, '500.000000')->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::RequiresReview, $payment->status);
        $this->assertSame('site_sold', $payment->review_reason);
        $this->assertNull($site->fresh()->paid_until);
    }

    public function test_domain_conflict_requires_review(): void
    {
        Site::factory()->create(['domain' => 'taken-domain.test']);

        $site = Site::factory()->create([
            'domain' => 'taken-domain.test',
            'status' => ListingStatus::Withdrawn,
            'paid_until' => null,
        ]);

        $payment = $this->payment($site, ['target_status' => ListingStatus::Active->value]);

        $this->sendCallback($payment, '500.000000')->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::RequiresReview, $payment->status);
        $this->assertSame('domain_conflict', $payment->review_reason);
        $this->assertNull($site->fresh()->paid_until);
        $this->assertSame(ListingStatus::Withdrawn, $site->fresh()->status);
    }

    public function test_unknown_invoice_is_acknowledged_without_changes(): void
    {
        $this->post(route('api.v1.payments.robokassa.result'), [
            'OutSum' => '500.000000',
            'InvId' => '999999',
            'SignatureValue' => md5('500.000000:999999:password-2'),
            'PaymentMethod' => 'SBP',
        ])->assertOk()->assertSee('OK999999');

        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_test_payment_callback_is_rejected_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $site = Site::factory()->create(['paid_until' => null]);
        $payment = $this->payment($site, ['is_test' => true]);

        $this->post(route('api.v1.payments.robokassa.result'), [
            'OutSum' => '500.000000',
            'InvId' => (string) $payment->id,
            'SignatureValue' => md5('500.000000:'.$payment->id.':test-password-2'),
            'PaymentMethod' => 'SBP',
        ])->assertStatus(400)->assertSee('ERROR');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_test_payment_callback_works_on_staging(): void
    {
        config(['services.robokassa.test_mode' => true]);

        $site = Site::factory()->create(['paid_until' => null]);
        $payment = $this->payment($site, ['is_test' => true, 'amount' => 500]);

        $this->post(route('api.v1.payments.robokassa.result'), [
            'OutSum' => '500.000000',
            'InvId' => (string) $payment->id,
            'SignatureValue' => md5('500.000000:'.$payment->id.':test-password-2'),
            'PaymentMethod' => 'SBP',
        ])->assertOk()->assertSee('OK'.$payment->id);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertNotNull($site->fresh()->paid_until);
    }

    public function test_target_inactive_status_keeps_site_hidden(): void
    {
        $site = Site::factory()->withdrawn()->create(['paid_until' => null]);
        $payment = $this->payment($site, [
            'target_status' => ListingStatus::Inactive->value,
            'amount' => 500,
        ]);

        $this->sendCallback($payment, '500.000000')->assertOk();

        $site->refresh();

        $this->assertSame(ListingStatus::Inactive, $site->status);
        $this->assertNotNull($site->paid_until);
        $this->assertFalse($site->isPubliclyVisible());
    }

    public function test_review_callback_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'permissions' => ['platform.marketplace.payments' => true],
        ]);

        $site = Site::factory()->create();
        $payment = $this->payment($site, ['amount' => 500]);

        $this->sendCallback($payment, '1.000000')->assertOk();

        Notification::assertSentTo(
            $admin,
            PaymentRequiresReview::class,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payment(Site $site, array $attributes = []): SubscriptionPayment
    {
        return SubscriptionPayment::factory()->for($site)->create(array_merge([
            'user_id' => $site->user_id,
            'amount' => 500,
            'base_price' => 500,
            'base_amount' => 500,
            'period_months' => 1,
            'target_status' => ListingStatus::Active->value,
            'is_test' => false,
            'status' => PaymentStatus::Pending,
        ], $attributes));
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function sendCallback(SubscriptionPayment $payment, string $outSum, array $extra = [])
    {
        $isTest = (bool) $payment->is_test;
        $password2 = $isTest ? 'test-password-2' : 'password-2';

        return $this->post(route('api.v1.payments.robokassa.result'), array_merge([
            'OutSum' => $outSum,
            'InvId' => (string) $payment->id,
            'SignatureValue' => md5($outSum.':'.$payment->id.':'.$password2),
            'PaymentMethod' => 'SBP',
            'IncCurrLabel' => 'RUB',
        ], $extra));
    }
}
