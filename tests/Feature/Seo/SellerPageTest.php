<?php

namespace Tests\Feature\Seo;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_page_is_rendered_with_meta_and_initial_state(): void
    {
        $seller = User::factory()->create([
            'name' => 'Иван Петров',
            'bio' => 'Продаю готовые сайты с доходом.',
        ]);
        Site::factory()->paid()->create(['user_id' => $seller->id]);

        $response = $this->get('/seller/'.$seller->id);

        $response->assertOk();
        $response->assertSee('<title>', false);
        $response->assertSee('Иван Петров', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee(url('/seller/'.$seller->id), false);
        $response->assertSee('og:title', false);
        $response->assertSee('og:type" content="profile', false);
        $response->assertSee('index, follow', false);
        $response->assertSee('"@type":"Person"', false);
        $response->assertSee('__INITIAL_STATE__', false);
        $response->assertSee('publishedSitesCount', false);
    }

    public function test_seller_page_uses_fallback_description_without_bio(): void
    {
        $seller = User::factory()->create(['name' => 'Пётр Иванов', 'bio' => null]);

        $this->get('/seller/'.$seller->id)
            ->assertOk()
            ->assertSee('Профиль продавца Пётр Иванов', false);
    }

    public function test_unknown_seller_page_returns_not_found(): void
    {
        $this->get('/seller/999999')->assertNotFound();
    }
}
