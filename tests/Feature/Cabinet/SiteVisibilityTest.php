<?php

namespace Tests\Feature\Cabinet;

use App\Models\Conversation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sold_site_can_be_hidden(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->postJson("/api/v1/my/sites/{$site->id}/hide")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertNull($site->fresh()->hidden_at);
    }

    public function test_hidden_sold_site_moves_between_lists(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->sold()->create();

        $this->actingAs($owner)
            ->postJson("/api/v1/my/sites/{$site->id}/hide")
            ->assertOk()
            ->assertJsonPath('data.hiddenAt', fn ($value) => $value !== null);

        $this->assertNotNull($site->fresh()->hidden_at);

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites?hidden=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $site->id);
    }

    public function test_restore_returns_site_to_list(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->sold()->hidden()->create();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/my/sites/{$site->id}/hide")
            ->assertOk()
            ->assertJsonPath('data.hiddenAt', null);

        $this->assertNull($site->fresh()->hidden_at);

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $site->id);
    }

    public function test_hiding_keeps_status_payments_and_conversations(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->sold()->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($owner)
            ->postJson("/api/v1/my/sites/{$site->id}/hide")
            ->assertOk();

        $fresh = $site->fresh();

        $this->assertSame('sold', $fresh->status->value);
        $this->assertNotNull($fresh->paid_until);
        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }

    public function test_foreign_site_cannot_be_hidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $site = Site::factory()->for($other, 'owner')->sold()->create();

        $this->actingAs($owner)
            ->postJson("/api/v1/my/sites/{$site->id}/hide")
            ->assertNotFound();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/my/sites/{$site->id}/hide")
            ->assertNotFound();
    }
}
