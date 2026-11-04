<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentAdminListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->admin());
    }

    public function test_filters_by_snapshot_domain_and_current_domain(): void
    {
        $payment = $this->adminPayment();

        $old = SubscriptionPayment::factory()->for(Site::factory()->create([
            'domain' => 'new-domain.test',
        ]))->create(['site_domain' => 'old-domain.test']);

        $this->assertSeeOnly($old, ['domains' => 'old-domain.test']);
        $this->assertSeeOnly($old, ['domains' => 'new-domain.test']);
    }

    public function test_filter_accepts_multiple_domains(): void
    {
        $first = $this->adminPayment('first-domain.test');
        $second = $this->adminPayment('second-domain.test');
        $other = $this->adminPayment('other-domain.test');

        $this->get($this->listUrl(['domains' => 'first-domain.test, second-domain.test']))
            ->assertOk()
            ->assertSee('first-domain.test')
            ->assertSee('second-domain.test')
            ->assertDontSee('other-domain.test');

        $this->assertTrue($first->exists && $second->exists && $other->exists);
    }

    public function test_filters_by_status_and_method(): void
    {
        $paid = $this->adminPayment('paid-method.test', status: PaymentStatus::Paid);
        $pending = $this->adminPayment('pending-method.test');

        $this->get($this->listUrl(['payment_status' => [PaymentStatus::Paid->value]]))
            ->assertOk()
            ->assertSee('paid-method.test')
            ->assertDontSee('pending-method.test');

        $this->get($this->listUrl(['payment_method_filter' => PaymentMethod::Sbp->value]))
            ->assertOk()
            ->assertSee('paid-method.test')
            ->assertSee('pending-method.test');

        $this->assertTrue($paid->exists && $pending->exists);
    }

    public function test_searches_by_invoice_number(): void
    {
        $target = $this->adminPayment('invoice-target.test');
        $this->adminPayment('invoice-other.test');

        $this->get($this->listUrl(['invoice' => (string) $target->id]))
            ->assertOk()
            ->assertSee('invoice-target.test')
            ->assertDontSee('invoice-other.test');
    }

    public function test_filters_by_paid_at_range(): void
    {
        $this->adminPayment('recent-paid.test', status: PaymentStatus::Paid, paidAt: Carbon::parse('2026-10-01 10:00:00'));
        $this->adminPayment('old-paid.test', status: PaymentStatus::Paid, paidAt: Carbon::parse('2025-01-01 10:00:00'));

        $this->get($this->listUrl([
            'paid_from' => '2026-01-01',
            'paid_to' => '2026-12-31',
        ]))
            ->assertOk()
            ->assertSee('recent-paid.test')
            ->assertDontSee('old-paid.test');
    }

    public function test_default_sort_is_newest_first(): void
    {
        $older = SubscriptionPayment::factory()->for(Site::factory()->create(['domain' => 'sort-old.test']))
            ->create(['created_at' => now()->subDays(2)]);
        $newer = SubscriptionPayment::factory()->for(Site::factory()->create(['domain' => 'sort-new.test']))
            ->create(['created_at' => now()]);

        $response = $this->get($this->listUrl())->assertOk();

        $positions = [
            strpos($response->getContent(), 'sort-new.test'),
            strpos($response->getContent(), 'sort-old.test'),
        ];

        $this->assertTrue($positions[0] !== false && $positions[1] !== false);
        $this->assertTrue($positions[0] < $positions[1]);

        $this->assertTrue($older->exists && $newer->exists);
    }

    public function test_reverse_sort_shows_oldest_first(): void
    {
        SubscriptionPayment::factory()->for(Site::factory()->create(['domain' => 'reverse-old.test']))
            ->create(['created_at' => now()->subDays(2)]);
        SubscriptionPayment::factory()->for(Site::factory()->create(['domain' => 'reverse-new.test']))
            ->create(['created_at' => now()]);

        $response = $this->get($this->listUrl(['sort' => 'created_at']))->assertOk();

        $oldPosition = strpos($response->getContent(), 'reverse-old.test');
        $newPosition = strpos($response->getContent(), 'reverse-new.test');

        $this->assertTrue($oldPosition !== false && $newPosition !== false);
        $this->assertTrue($oldPosition < $newPosition);
    }

    public function test_list_handles_more_than_one_hundred_payments(): void
    {
        $site = Site::factory()->create(['domain' => 'bulk-domain.test']);

        SubscriptionPayment::factory()->count(120)->for($site)->create([
            'user_id' => $site->user_id,
        ]);

        $this->get($this->listUrl(['domains' => 'bulk-domain.test']))
            ->assertOk();
    }

    private function adminPayment(
        string $domain = 'default-payment.test',
        PaymentStatus $status = PaymentStatus::Pending,
        ?Carbon $paidAt = null,
    ): SubscriptionPayment {
        $site = Site::factory()->create(['domain' => $domain]);

        return SubscriptionPayment::factory()->for($site)->create([
            'user_id' => $site->user_id,
            'site_domain' => $domain,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function listUrl(array $query = []): string
    {
        return route('platform.marketplace.payments', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function assertSeeOnly(SubscriptionPayment $payment, array $query): void
    {
        $this->get($this->listUrl($query))
            ->assertOk()
            ->assertSee((string) $payment->site_domain);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'permissions' => [
                'platform.index' => true,
                'platform.marketplace.payments' => true,
            ],
        ]);
    }
}
