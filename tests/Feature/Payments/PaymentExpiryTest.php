<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaymentExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_cancels_expired_pending_attempts(): void
    {
        $site = Site::factory()->create(['paid_until' => null]);
        $expired = SubscriptionPayment::factory()->for($site)->expired()->create();
        $alive = SubscriptionPayment::factory()->for($site)->create();

        $this->artisan('payments:expire-pending')->assertSuccessful();

        $this->assertSame(PaymentStatus::Cancelled, $expired->fresh()->status);
        $this->assertSame('expired', $expired->fresh()->failure_reason);
        $this->assertSame(PaymentStatus::Pending, $alive->fresh()->status);
        $this->assertNull($site->fresh()->paid_until);
    }

    public function test_status_endpoint_expires_attempt_lazily(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->create(['paid_until' => null]);
        $payment = SubscriptionPayment::factory()->for($site)->expired()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.v1.my-payments.show', $payment))
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Cancelled->value)
            ->assertJsonPath('data.failureReason', 'expired');

        $this->assertNull($site->fresh()->paid_until);
    }

    public function test_payment_options_expire_due_attempts(): void
    {
        Cache::flush();

        SubscriptionSetting::query()->updateOrCreate(['id' => 1], [
            'base_price' => 500,
            'periods' => [1],
            'discounts' => [],
            'target_status' => 'active',
            'qr_ttl_minutes' => 15,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $site = Site::factory()->for($user, 'owner')->create();
        $payment = SubscriptionPayment::factory()->for($site)->expired()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.v1.my-sites.payment-options', $site))
            ->assertOk()
            ->assertJsonPath('canPay', true)
            ->assertJsonPath('activePayment', null);

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
    }

    public function test_paid_attempts_are_not_expired(): void
    {
        $site = Site::factory()->create();
        $payment = SubscriptionPayment::factory()->for($site)->paid()->create([
            'qr_expires_at' => now()->subHour(),
        ]);

        $this->artisan('payments:expire-pending')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }
}
