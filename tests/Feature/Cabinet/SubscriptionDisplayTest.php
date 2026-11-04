<?php

namespace Tests\Feature\Cabinet;

use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_marker_uses_seven_day_threshold(): void
    {
        $owner = User::factory()->create();

        $cases = [
            'expired' => [today()->subDay(), true],
            'today' => [today(), true],
            'in 6 days' => [today()->addDays(6), true],
            'in 7 days' => [today()->addDays(7), true],
            'in 8 days' => [today()->addDays(8), false],
            'unpaid' => [null, true],
        ];

        foreach ($cases as $label => [$paidUntil, $expected]) {
            $site = Site::factory()->for($owner, 'owner')->create(['paid_until' => $paidUntil]);

            $this->actingAs($owner)
                ->getJson("/api/v1/my/sites/{$site->id}")
                ->assertOk()
                ->assertJsonPath('data.needsRenewal', $expected);
        }
    }

    public function test_payment_history_is_shown_newest_first(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();

        $older = SubscriptionPayment::factory()->for($site)->paid()->create([
            'created_at' => now()->subDay(),
        ]);
        $newer = SubscriptionPayment::factory()->for($site)->paid()->create([
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/my/sites/{$site->id}")
            ->assertOk()
            ->assertJsonPath('data.payments.0.id', $newer->id)
            ->assertJsonPath('data.payments.1.id', $older->id);
    }

    public function test_expired_site_is_hidden_from_catalog_but_present_in_cabinet(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->expired()->create();

        $this->getJson('/api/v1/catalog')
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $site->id)
            ->assertJsonPath('data.0.needsRenewal', true);
    }

    public function test_list_exposes_latest_payment_and_renewal_ability(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create(['paid_until' => today()->addDays(30)]);

        $latest = SubscriptionPayment::factory()->for($site)->create([
            'user_id' => $owner->id,
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonPath('data.0.canRenew', true)
            ->assertJsonPath('data.0.latestPayment.id', $latest->id)
            ->assertJsonPath('data.0.latestPayment.status', 'pending');
    }

    public function test_sold_site_cannot_be_renewed(): void
    {
        $owner = User::factory()->create();
        Site::factory()->for($owner, 'owner')->sold()->create();

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonPath('data.0.canRenew', false);
    }

    public function test_payment_history_endpoint_returns_attempts(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();

        $payment = SubscriptionPayment::factory()->for($site)->paid()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/my/sites/{$site->id}/payments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $payment->id)
            ->assertJsonPath('data.0.statusLabel', 'Оплачен');
    }

    public function test_foreign_site_payment_history_is_hidden(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/my/sites/{$site->id}/payments")
            ->assertNotFound();
    }
}
