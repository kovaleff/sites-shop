<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/notification-settings')->assertUnauthorized();
    }

    public function test_all_types_are_enabled_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/notification-settings')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.value', NotificationType::Messages->value)
            ->assertJsonPath('data.0.enabled', true)
            ->assertJsonPath('data.3.value', NotificationType::Reports->value)
            ->assertJsonPath('data.3.enabled', true)
            ->assertJsonPath('email', $user->email);
    }

    public function test_setting_can_be_disabled_without_touching_the_others(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/notification-settings', [
                'settings' => [NotificationType::Messages->value => false],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.value', NotificationType::Messages->value)
            ->assertJsonPath('data.0.enabled', false)
            ->assertJsonPath('data.1.enabled', true);

        $user->refresh();

        $this->assertFalse($user->wantsNotification(NotificationType::Messages));
        $this->assertTrue($user->wantsNotification(NotificationType::Moderation));
    }

    public function test_unknown_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/notification-settings', [
                'settings' => ['unknown' => false],
            ])
            ->assertUnprocessable();
    }

    public function test_non_boolean_value_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/notification-settings', [
                'settings' => [NotificationType::Messages->value => 'yes'],
            ])
            ->assertUnprocessable();
    }

    public function test_signed_unsubscribe_link_disables_the_type_without_login(): void
    {
        $user = User::factory()->create();

        $url = URL::signedRoute('notifications.unsubscribe', [
            'type' => NotificationType::Messages->value,
            'user' => $user->id,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Уведомления отключены');

        $this->assertFalse($user->refresh()->wantsNotification(NotificationType::Messages));
        $this->assertTrue($user->wantsNotification(NotificationType::Moderation));
    }

    public function test_unsubscribe_without_a_valid_signature_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->get('/notifications/unsubscribe/messages/'.$user->id)
            ->assertForbidden();

        $this->assertTrue($user->refresh()->wantsNotification(NotificationType::Messages));
    }
}
