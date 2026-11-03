<?php

namespace Tests\Feature\Cabinet;

use App\Models\Conversation;
use App\Models\Review;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_use_review_endpoints(): void
    {
        $site = Site::factory()->sold()->paid()->create();

        $this->getJson('/api/v1/reviews/pending')->assertUnauthorized();
        $this->getJson('/api/v1/reviews/mine')->assertUnauthorized();
        $this->postJson('/api/v1/sites/'.$site->id.'/reviews', ['rating' => 5])->assertUnauthorized();
    }

    public function test_buyer_with_conversation_can_leave_a_review_on_a_sold_site(): void
    {
        $seller = User::factory()->create();
        $buyer = $this->eligibleBuyer();
        $site = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);
        Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/sites/'.$site->id.'/reviews', [
                'rating' => 5,
                'comment' => 'Сделка прошла быстро, продавец на связи.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.isPublished', false);

        $this->assertDatabaseHas('reviews', [
            'site_id' => $site->id,
            'author_id' => $buyer->id,
            'seller_id' => $seller->id,
            'rating' => 5,
            'moderation_status' => 'pending',
        ]);

        // Отзыв на модерации не влияет на публичный рейтинг продавца.
        $this->assertSame(0, $seller->refresh()->reviews_count);
        $this->assertNull($seller->rating_avg);
    }

    public function test_review_requires_a_sold_site(): void
    {
        $buyer = $this->eligibleBuyer();
        $site = Site::factory()->paid()->create();
        Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/sites/'.$site->id.'/reviews', ['rating' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');
    }

    public function test_non_participant_cannot_leave_a_review(): void
    {
        $site = Site::factory()->sold()->paid()->create();
        $stranger = $this->eligibleBuyer();

        $this->actingAs($stranger)
            ->postJson('/api/v1/sites/'.$site->id.'/reviews', ['rating' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_seller_cannot_leave_a_review_about_own_site(): void
    {
        $seller = $this->eligibleBuyer();
        $site = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);

        $this->actingAs($seller)
            ->postJson('/api/v1/sites/'.$site->id.'/reviews', ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');
    }

    public function test_buyer_cannot_leave_two_reviews_for_the_same_site(): void
    {
        $buyer = $this->eligibleBuyer();
        $site = Site::factory()->sold()->paid()->create();
        Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/sites/'.$site->id.'/reviews', ['rating' => 5])
            ->assertCreated();

        $this->actingAs($buyer)
            ->postJson('/api/v1/sites/'.$site->id.'/reviews', ['rating' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_new_accounts_cannot_leave_reviews(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create(['created_at' => now()->subDay()]);
        $site = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);
        Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/sites/'.$site->id.'/reviews', ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');
    }

    public function test_comment_with_contacts_is_rejected(): void
    {
        $buyer = $this->eligibleBuyer();
        $site = Site::factory()->sold()->paid()->create();
        Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/sites/'.$site->id.'/reviews', [
                'rating' => 5,
                'comment' => 'Пишите мне в telegram для сделок.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');
    }

    public function test_pending_list_contains_only_sold_sites_with_conversation_and_without_review(): void
    {
        $buyer = $this->eligibleBuyer();
        $seller = User::factory()->create();

        $available = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);
        Conversation::factory()->forSite($available, $buyer)->create();

        $reviewed = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);
        Conversation::factory()->forSite($reviewed, $buyer)->create();
        Review::factory()->forSite($reviewed, $buyer)->create();

        $active = Site::factory()->paid()->create(['user_id' => $seller->id]);
        Conversation::factory()->forSite($active, $buyer)->create();

        Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);

        $this->actingAs($buyer)
            ->getJson('/api/v1/reviews/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id);
    }

    public function test_my_reviews_include_reviews_on_moderation(): void
    {
        $buyer = $this->eligibleBuyer();
        $site = Site::factory()->sold()->paid()->create();
        Review::factory()->forSite($site, $buyer)->create(['rating' => 3]);

        $this->actingAs($buyer)
            ->getJson('/api/v1/reviews/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 3)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.site.id', $site->id);
    }

    public function test_seller_reviews_endpoint_returns_only_published_reviews(): void
    {
        $seller = User::factory()->create();
        $published = Review::factory()->published()->create([
            'seller_id' => $seller->id,
            'rating' => 5,
            'site_id' => Site::factory()->sold()->paid()->create(['user_id' => $seller->id])->id,
        ]);
        Review::factory()->create([
            'seller_id' => $seller->id,
            'site_id' => Site::factory()->sold()->paid()->create(['user_id' => $seller->id])->id,
        ]);
        Review::factory()->rejected()->create([
            'seller_id' => $seller->id,
            'site_id' => Site::factory()->sold()->paid()->create(['user_id' => $seller->id])->id,
        ]);

        $this->getJson('/api/v1/sellers/'.$seller->id.'/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_seller_reviews_endpoint_returns_not_found_for_unknown_seller(): void
    {
        $this->getJson('/api/v1/sellers/999999/reviews')->assertNotFound();
    }

    public function test_approved_review_updates_seller_rating_aggregate(): void
    {
        $seller = User::factory()->create();
        $site = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);
        $review = Review::factory()->create([
            'site_id' => $site->id,
            'seller_id' => $seller->id,
            'author_id' => $this->eligibleBuyer()->id,
            'rating' => 4,
        ]);

        $review->approve();

        $seller->refresh();

        $this->assertSame(1, $seller->reviews_count);
        $this->assertEquals(4.0, (float) $seller->rating_avg);

        $review->reject();

        $seller->refresh();

        $this->assertSame(0, $seller->reviews_count);
        $this->assertNull($seller->rating_avg);
    }

    public function test_public_seller_and_listing_payloads_expose_rating(): void
    {
        $seller = User::factory()->create([
            'rating_avg' => 4.5,
            'reviews_count' => 2,
        ]);
        $site = Site::factory()->paid()->create(['user_id' => $seller->id]);

        $this->getJson('/api/v1/sellers/'.$seller->id)
            ->assertOk()
            ->assertJsonPath('data.ratingAvg', 4.5)
            ->assertJsonPath('data.reviewsCount', 2)
            ->assertJsonPath('listings.0.seller.ratingAvg', 4.5)
            ->assertJsonPath('listings.0.seller.reviewsCount', 2);

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug())
            ->assertOk()
            ->assertJsonPath('data.seller.ratingAvg', 4.5)
            ->assertJsonPath('data.seller.reviewsCount', 2);
    }

    public function test_deleting_site_removes_its_reviews_and_resets_seller_rating(): void
    {
        $seller = User::factory()->create();
        $site = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);
        Review::factory()->published()->forSite($site, $this->eligibleBuyer())->create(['rating' => 5]);

        $this->assertSame(1, $seller->refresh()->reviews_count);

        $site->delete();

        $this->assertDatabaseCount('reviews', 0);
        $this->assertSame(0, $seller->refresh()->reviews_count);
        $this->assertNull($seller->rating_avg);
    }

    private function eligibleBuyer(): User
    {
        return User::factory()->create([
            'created_at' => now()->subDays(30),
        ]);
    }
}
