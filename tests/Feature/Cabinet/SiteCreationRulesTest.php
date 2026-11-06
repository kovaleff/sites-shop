<?php

namespace Tests\Feature\Cabinet;

use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteCreationRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Магазин товаров для дома',
            'domain' => 'example-creation.ru',
            'description' => str_repeat('Описание объявления. ', 4),
            'price' => 1250000,
            'monthlyRevenue' => 85000,
            'hasTrademark' => false,
            'categories' => [],
            'acceptOffer' => true,
        ], $overrides);
    }

    private function categoryId(): int
    {
        return Category::factory()->create()->id;
    }

    public function test_created_site_starts_on_moderation(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'categories' => [$this->categoryId()],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.moderationStatus', ModerationStatus::Pending->value)
            ->assertJsonPath('data.verified', false);

        $this->assertDatabaseHas('sites', [
            'user_id' => $owner->id,
            'moderation_status' => ModerationStatus::Pending->value,
        ]);
    }

    public function test_offer_acceptance_is_required_to_create_site(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'acceptOffer' => false,
                'categories' => [$this->categoryId()],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('acceptOffer');

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_offer_acceptance_is_not_required_to_update_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}", $this->payload([
                'acceptOffer' => false,
                'categories' => [$this->categoryId()],
            ]))
            ->assertOk();
    }

    public function test_links_in_title_are_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'title' => 'Срочно продам сайт https://spam.example',
                'categories' => [$this->categoryId()],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_contacts_in_description_are_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'description' => str_repeat('Описание объявления. ', 3).'Звоните +7 (999) 123-45-67',
                'categories' => [$this->categoryId()],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_forbidden_topic_is_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'title' => 'Казино с бонусами',
                'categories' => [$this->categoryId()],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_listing_limit_is_enforced(): void
    {
        $owner = User::factory()->create();
        Site::factory()->count(3)->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'categories' => [$this->categoryId()],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_creation_is_throttled(): void
    {
        config([
            'sites.max_active_per_user' => 0,
            'sites.creation_per_day' => 1,
        ]);

        $owner = User::factory()->create();
        $category = $this->categoryId();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'domain' => 'first-throttle.ru',
                'categories' => [$category],
            ]))
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'domain' => 'second-throttle.ru',
                'categories' => [$category],
            ]))
            ->assertTooManyRequests();
    }

    public function test_domain_change_restarts_verification(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->verified()->create([
            'domain' => 'old-domain.ru',
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}", $this->payload([
                'domain' => 'new-domain.ru',
                'categories' => [$this->categoryId()],
            ]))
            ->assertOk();

        $site->refresh();

        $this->assertFalse($site->verified);
        $this->assertSame(ModerationStatus::Pending, $site->moderation_status);
    }

    public function test_captcha_is_required_when_configured(): void
    {
        config(['sites.captcha.server_key' => 'secret']);

        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'categories' => [$this->categoryId()],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('captchaToken');
    }

    public function test_valid_captcha_token_allows_creation(): void
    {
        config(['sites.captcha.server_key' => 'secret']);

        Http::fake([
            'smartcaptcha.yandexcloud.net/*' => Http::response(['status' => 'ok']),
        ]);

        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'categories' => [$this->categoryId()],
                'captchaToken' => 'valid-token',
            ]))
            ->assertCreated();
    }

    public function test_rejected_captcha_token_blocks_creation(): void
    {
        config(['sites.captcha.server_key' => 'secret']);

        Http::fake([
            'smartcaptcha.yandexcloud.net/*' => Http::response(['status' => 'failed']),
        ]);

        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'categories' => [$this->categoryId()],
                'captchaToken' => 'bad-token',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('captchaToken');
    }
}
