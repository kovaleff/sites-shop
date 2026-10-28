<?php

declare(strict_types=1);

namespace Tests\Feature\Orchid;

use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;

class SubscriptionPaymentReadOnlyTest extends OrchidTestCase
{
    public function test_payment_list_shows_site_payer_amount_and_status(): void
    {
        $this->actingAs($this->admin());

        $payer = User::factory()->create(['name' => 'Payer Person']);
        $site = Site::factory()->create([
            'domain' => 'payment-site.test',
            'user_id' => $payer->id,
        ]);

        SubscriptionPayment::factory()->for($site)->paid()->create([
            'user_id' => $payer->id,
            'amount' => 1234.56,
            'period_months' => 6,
        ]);

        $this->get(route('platform.marketplace.payments'))
            ->assertOk()
            ->assertSee('payment-site.test')
            ->assertSee('Payer Person')
            ->assertSee('1 234.56')
            ->assertSee('RUB')
            ->assertSee(PaymentStatus::Paid->label());
    }

    public function test_payments_can_be_filtered_by_status(): void
    {
        $this->actingAs($this->admin());

        $paid = SubscriptionPayment::factory()
            ->for(Site::factory()->create(['domain' => 'paid-site.test']))
            ->paid()
            ->create();

        $failed = SubscriptionPayment::factory()
            ->for(Site::factory()->create(['domain' => 'failed-site.test']))
            ->failed()
            ->create();

        $this->get(route('platform.marketplace.payments', [
            'filter' => ['status' => [PaymentStatus::Paid->value]],
        ]))
            ->assertOk()
            ->assertSee('paid-site.test')
            ->assertDontSee('failed-site.test');

        $this->get(route('platform.marketplace.payments', [
            'filter' => ['status' => [PaymentStatus::Failed->value]],
        ]))
            ->assertOk()
            ->assertSee('failed-site.test')
            ->assertDontSee('paid-site.test');
    }

    public function test_payments_can_be_filtered_by_site_and_payer(): void
    {
        $this->actingAs($this->admin());

        $payer = User::factory()->create(['name' => 'Unique Payer']);

        SubscriptionPayment::factory()
            ->for(Site::factory()->create(['domain' => 'alpha-payment.test']))
            ->create(['user_id' => $payer->id]);

        SubscriptionPayment::factory()
            ->for(Site::factory()->create(['domain' => 'beta-payment.test']))
            ->create(['user_id' => User::factory()->create(['name' => 'Other Payer'])->id]);

        $this->get(route('platform.marketplace.payments', ['site' => 'alpha-payment']))
            ->assertOk()
            ->assertSee('alpha-payment.test')
            ->assertDontSee('beta-payment.test');

        $this->get(route('platform.marketplace.payments', ['payer' => 'Unique Payer']))
            ->assertOk()
            ->assertSee('alpha-payment.test')
            ->assertDontSee('beta-payment.test');
    }

    public function test_payments_can_be_filtered_by_payment_date(): void
    {
        $this->actingAs($this->admin());

        SubscriptionPayment::factory()
            ->for(Site::factory()->create(['domain' => 'recent-payment.test']))
            ->paid()
            ->create(['paid_at' => Carbon::parse('2026-10-01 10:00:00')]);

        SubscriptionPayment::factory()
            ->for(Site::factory()->create(['domain' => 'old-payment.test']))
            ->paid()
            ->create(['paid_at' => Carbon::parse('2025-01-01 10:00:00')]);

        $this->get(route('platform.marketplace.payments', [
            'filter' => [
                'paid_at' => ['start' => '2026-01-01', 'end' => '2026-12-31'],
            ],
        ]))
            ->assertOk()
            ->assertSee('recent-payment.test')
            ->assertDontSee('old-payment.test');
    }

    public function test_payment_card_shows_site_paid_until(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->create([
            'domain' => 'paid-until-site.test',
            'paid_until' => Carbon::parse('2027-03-15'),
        ]);

        $payment = SubscriptionPayment::factory()->for($site)->paid()->create();

        $this->get(route('platform.marketplace.payments.show', $payment))
            ->assertOk()
            ->assertSee('paid-until-site.test')
            ->assertSee('15.03.2027');
    }

    public function test_payment_screens_have_no_write_actions(): void
    {
        $this->actingAs($this->admin());

        $payment = SubscriptionPayment::factory()
            ->for(Site::factory()->create())
            ->paid()
            ->create();

        $this->post(route('platform.marketplace.payments', ['method' => 'save']))
            ->assertStatus(405);

        $this->post(route('platform.marketplace.payments', ['method' => 'remove']))
            ->assertStatus(405);

        $this->post(route('platform.marketplace.payments.show', [$payment, 'save']))
            ->assertStatus(405);

        $this->assertDatabaseHas('subscription_payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Paid->value,
        ]);
    }
}
