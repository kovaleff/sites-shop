<?php

namespace Tests\Feature\Cabinet;

use App\Models\Category;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Магазин товаров для дома',
            'domain' => 'example-check.ru',
            'description' => str_repeat('Описание объявления. ', 4),
            'price' => 1250000,
            'monthlyRevenue' => 85000,
            'hasTrademark' => false,
            'categories' => [],
        ], $overrides);
    }

    public function test_guest_cannot_create_site(): void
    {
        $this->postJson('/api/v1/my/sites', $this->payload())->assertUnauthorized();
    }

    public function test_empty_payload_returns_validation_errors(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title', 'domain', 'description', 'price', 'monthlyRevenue', 'categories',
            ]);
    }

    public function test_title_bounds_are_enforced(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'title' => 'ab',
                'categories' => [$category->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'title' => str_repeat('а', 256),
                'categories' => [$category->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_description_bounds_are_enforced(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'description' => 'коротко',
                'categories' => [$category->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'description' => str_repeat('а', 5001),
                'categories' => [$category->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_negative_price_and_revenue_are_rejected(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'price' => -1,
                'monthlyRevenue' => -100,
                'categories' => [$category->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'monthlyRevenue']);
    }

    public function test_at_least_one_category_is_required(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload(['categories' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categories');
    }

    public function test_invalid_domain_is_rejected(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'domain' => 'http://example.ru/path',
                'categories' => [$category->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    }

    public function test_duplicate_domain_is_rejected_after_normalization(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        Site::factory()->create(['domain' => 'taken.ru']);

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'domain' => 'WWW.TAKEN.RU',
                'categories' => [$category->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    }

    public function test_domain_of_withdrawn_site_can_be_reused(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        Site::factory()->withdrawn()->create(['domain' => 'free.ru']);

        $this->actingAs($owner)
            ->postJson('/api/v1/my/sites', $this->payload([
                'domain' => 'free.ru',
                'categories' => [$category->id],
            ]))
            ->assertCreated();
    }

    public function test_created_site_has_owner_active_status_and_verification_file(): void
    {
        $owner = User::factory()->create();
        $first = Category::factory()->create();
        $second = Category::factory()->create();

        $response = $this->actingAs($owner)->postJson('/api/v1/my/sites', $this->payload([
            'categories' => [$first->id, $second->id],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.statusLabel', 'Активен')
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.isPubliclyVisible', false);

        $site = Site::query()->findOrFail($response->json('data.id'));

        $this->assertSame($owner->id, $site->user_id);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}\.txt$/', $site->verification_filename);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $site->categories->pluck('id')->all());
    }

    public function test_verification_file_names_are_unique(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $first = $this->actingAs($owner)->postJson('/api/v1/my/sites', $this->payload([
            'domain' => 'one.ru',
            'categories' => [$category->id],
        ]));
        $second = $this->actingAs($owner)->postJson('/api/v1/my/sites', $this->payload([
            'domain' => 'two.ru',
            'categories' => [$category->id],
        ]));

        $this->assertNotSame(
            $first->json('data.verificationFilename'),
            $second->json('data.verificationFilename'),
        );
    }

    public function test_sold_site_cannot_be_edited(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->sold()->create();

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}", $this->payload([
                'categories' => [$category->id],
            ]))
            ->assertUnprocessable();
    }

    public function test_owner_can_update_site_and_keep_same_domain(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create(['domain' => 'same.ru']);

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}", $this->payload([
                'domain' => 'same.ru',
                'price' => 999,
                'categories' => [$category->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.price', 999);
    }

    public function test_foreign_site_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->create();
        $site = Site::factory()->for($other, 'owner')->create();

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}", $this->payload([
                'categories' => [$category->id],
            ]))
            ->assertNotFound();
    }
}
