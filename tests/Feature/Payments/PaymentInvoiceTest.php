<?php

namespace Tests\Feature\Payments;

use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaymentInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->configureRobokassa();
        $this->configureTariff();
    }

    public function test_owner_can_create_invoice_with_snapshot_and_start_payload(): void
    {
        $user = User::factory()->create(['email' => 'seller@example.com']);
        $site = Site::factory()->for($user, 'owner')->create([
            'domain' => 'invoice-site.test',
            'status' => ListingStatus::Withdrawn,
            'paid_until' => null,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 3, 'receiptEmail' => 'receipt@example.com'],
        );

        $response->assertCreated()
            ->assertJsonPath('data.amount', 1350)
            ->assertJsonPath('data.periodMonths', 3)
            ->assertJsonPath('data.siteDomain', 'invoice-site.test')
            ->assertJsonPath('data.status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.targetStatus', ListingStatus::Active->value)
            ->assertJsonPath('robokassa.outSum', '1350')
            ->assertJsonPath('robokassa.invId', 1)
            ->assertJsonPath('robokassa.merchantLogin', 'test-shop')
            ->assertJsonPath('robokassa.isTest', false);

        $this->assertNotEmpty($response->json('robokassa.signature'));
        $this->assertStringContainsString('invoice-site.test', $response->json('robokassa.receipt'));

        $this->assertDatabaseHas('subscription_payments', [
            'site_id' => $site->id,
            'site_domain' => 'invoice-site.test',
            'base_price' => 500,
            'base_amount' => 1500,
            'discount_percent' => 10,
            'discount_amount' => 150,
            'amount' => 1350,
            'target_status' => ListingStatus::Active->value,
            'receipt_email' => 'receipt@example.com',
            'payment_method' => 'sbp',
            'provider' => 'robokassa',
            'is_test' => 0,
        ]);

        $this->assertNotNull(
            SubscriptionPayment::query()->first()->qr_expires_at,
        );
    }

    public function test_guest_cannot_create_invoice(): void
    {
        $site = Site::factory()->create();

        $this->postJson(route('api.v1.my-sites.payments.store', $site), [
            'periodMonths' => 1,
            'receiptEmail' => 'guest@example.com',
        ])->assertUnauthorized();
    }

    public function test_foreign_site_is_not_found(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();

        $this->actingAs($stranger)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1, 'receiptEmail' => 'stranger@example.com'],
        )->assertNotFound();
    }

    public function test_sold_site_is_rejected_with_explanation(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->sold()->create();

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1, 'receiptEmail' => 'seller@example.com'],
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'sold');
    }

    public function test_withdrawn_site_can_be_paid(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->withdrawn()->create();

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1],
        )->assertCreated();
    }

    public function test_receipt_email_required_for_technical_account_email(): void
    {
        $user = User::factory()->create(['email' => 'seller@vk.users']);
        $site = Site::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1],
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'receipt_email_required');
    }

    public function test_receipt_email_defaults_to_deliverable_account_email(): void
    {
        $user = User::factory()->create(['email' => 'account@example.com']);
        $site = Site::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1],
        )->assertCreated();

        $this->assertDatabaseHas('subscription_payments', [
            'site_id' => $site->id,
            'receipt_email' => 'account@example.com',
        ]);
        $this->assertSame('account@example.com', $user->fresh()->email);
    }

    public function test_receipt_email_does_not_change_account_email(): void
    {
        $user = User::factory()->create(['email' => 'account@example.com']);
        $site = Site::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1, 'receiptEmail' => 'other@example.com'],
        )->assertCreated();

        $this->assertSame('account@example.com', $user->fresh()->email);
    }

    public function test_new_invoice_cancels_previous_pending_attempt(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->create();

        $first = SubscriptionPayment::factory()->for($site)->create([
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1],
        )->assertCreated();

        $first->refresh();

        $this->assertSame(PaymentStatus::Cancelled, $first->status);
        $this->assertSame('new_attempt', $first->failure_reason);
        $this->assertSame(1, $site->subscriptionPayments()->where('status', PaymentStatus::Pending->value)->count());
    }

    public function test_payment_under_review_blocks_a_new_invoice(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->create();

        SubscriptionPayment::factory()->for($site)->requiresReview()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1],
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'under_review');
    }

    public function test_domain_conflict_blocks_invoice(): void
    {
        Site::factory()->create(['domain' => 'busy-domain.test']);

        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->withdrawn()->create([
            'domain' => 'busy-domain.test',
        ]);

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1],
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'domain_conflict');
    }

    public function test_inactive_tariff_blocks_invoice(): void
    {
        SubscriptionSetting::query()->update(['is_active' => false]);
        Cache::forget(SubscriptionSetting::CACHE_KEY);

        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 1],
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'tariff_inactive');
    }

    public function test_period_outside_configured_set_is_rejected(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->postJson(
            route('api.v1.my-sites.payments.store', $site),
            ['periodMonths' => 2],
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_period');
    }

    public function test_options_endpoint_returns_price_and_open_attempt(): void
    {
        $user = User::factory()->create(['email' => 'seller@example.com']);
        $site = Site::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->getJson(route('api.v1.my-sites.payment-options', $site))
            ->assertOk()
            ->assertJsonPath('canPay', true)
            ->assertJsonPath('basePrice', 500)
            ->assertJsonPath('targetStatus', 'active')
            ->assertJsonPath('willBeHidden', false)
            ->assertJsonPath('defaultReceiptEmail', 'seller@example.com')
            ->assertJsonCount(4, 'periods');
    }

    public function test_options_endpoint_marks_sold_site_as_not_payable(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->sold()->create();

        $this->actingAs($user)->getJson(route('api.v1.my-sites.payment-options', $site))
            ->assertOk()
            ->assertJsonPath('canPay', false)
            ->assertJsonPath('blockedReason', 'sold');
    }

    public function test_options_endpoint_reports_inactive_tariff_without_configured_price(): void
    {
        SubscriptionSetting::query()->delete();
        Cache::forget(SubscriptionSetting::CACHE_KEY);

        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->getJson(route('api.v1.my-sites.payment-options', $site))
            ->assertOk()
            ->assertJsonPath('canPay', false)
            ->assertJsonPath('blockedReason', 'tariff_inactive')
            ->assertJsonPath('basePrice', 0)
            ->assertJsonPath('periods', []);
    }

    public function test_history_endpoint_returns_payments_newest_first(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->create();

        $old = SubscriptionPayment::factory()->for($site)->create([
            'user_id' => $user->id,
            'created_at' => now()->subDay(),
        ]);
        $new = SubscriptionPayment::factory()->for($site)->paid()->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(
            route('api.v1.my-sites.payments.index', $site),
        )->assertOk();

        $this->assertSame([$new->id, $old->id], array_column($response->json('data'), 'id'));
    }

    public function test_foreign_payment_status_is_hidden(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $payment = SubscriptionPayment::factory()->for(Site::factory()->for($owner, 'owner'))->create();

        $this->actingAs($stranger)->getJson(route('api.v1.my-payments.show', $payment))
            ->assertNotFound();
    }

    private function configureTariff(): void
    {
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

    private function configureRobokassa(): void
    {
        config([
            'services.robokassa.merchant_login' => 'test-shop',
            'services.robokassa.password1' => 'password-1',
            'services.robokassa.password2' => 'password-2',
            'services.robokassa.test_mode' => false,
        ]);
    }
}
