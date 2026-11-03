<?php

namespace Tests\Feature\Orchid;

use App\Models\Review;
use App\Models\Site;
use App\Models\User;

class ReviewModerationTest extends OrchidTestCase
{
    public function test_review_list_shows_comments_and_authors(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->sold()->paid()->create(['domain' => 'reviewed-site.test']);
        $author = User::factory()->create(['name' => 'Покупатель Отзывов']);
        Review::factory()->forSite($site, $author)->create([
            'comment' => 'Быстрая сделка и честное описание.',
        ]);

        $this->get(route('platform.marketplace.reviews'))
            ->assertOk()
            ->assertSee('Быстрая сделка и честное описание.')
            ->assertSee('Покупатель Отзывов')
            ->assertSee('reviewed-site.test');
    }

    public function test_admin_can_publish_a_review_and_rating_is_recalculated(): void
    {
        $this->actingAs($this->admin());

        $seller = User::factory()->create();
        $site = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);
        $review = Review::factory()->forSite($site, User::factory()->create())->create([
            'rating' => 5,
        ]);

        $this->post(route('platform.marketplace.reviews', ['method' => 'approve']), [
            'review' => $review->id,
        ])->assertRedirect();

        $review->refresh();
        $seller->refresh();

        $this->assertTrue($review->isPublished());
        $this->assertSame(1, $seller->reviews_count);
        $this->assertEquals(5.0, (float) $seller->rating_avg);
    }

    public function test_admin_can_reject_a_review_and_rating_is_cleared(): void
    {
        $this->actingAs($this->admin());

        $seller = User::factory()->create();
        $site = Site::factory()->sold()->paid()->create(['user_id' => $seller->id]);
        $review = Review::factory()->published()->forSite($site, User::factory()->create())->create([
            'rating' => 4,
        ]);

        $this->post(route('platform.marketplace.reviews', ['method' => 'reject']), [
            'review' => $review->id,
        ])->assertRedirect();

        $review->refresh();
        $seller->refresh();

        $this->assertFalse($review->isPublished());
        $this->assertSame(0, $seller->reviews_count);
        $this->assertNull($seller->rating_avg);
    }
}
