<?php

declare(strict_types=1);

namespace Tests\Feature\Orchid;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;

class SiteManagementTest extends OrchidTestCase
{
    public function test_sites_can_be_filtered_by_domain(): void
    {
        $this->actingAs($this->admin());

        Site::factory()->create(['domain' => 'match-me.test']);
        Site::factory()->create(['domain' => 'other-site.test']);

        $this->get(route('platform.marketplace.sites', [
            'filter' => ['domain' => 'match-me'],
        ]))
            ->assertOk()
            ->assertSee('match-me.test')
            ->assertDontSee('other-site.test');
    }

    public function test_sites_can_be_filtered_by_status(): void
    {
        $this->actingAs($this->admin());

        Site::factory()->create(['domain' => 'alpha-active.test']);
        Site::factory()->inactive()->create(['domain' => 'beta-inactive.test']);

        $this->get(route('platform.marketplace.sites', [
            'filter' => ['status' => [ListingStatus::Inactive->value]],
        ]))
            ->assertOk()
            ->assertSee('beta-inactive.test')
            ->assertDontSee('alpha-active.test');
    }

    public function test_sites_can_be_filtered_by_category(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();
        $matching = Site::factory()->create(['domain' => 'matching-site.test']);
        $matching->categories()->attach($category);

        Site::factory()->create(['domain' => 'uncategorized-site.test']);

        $this->get(route('platform.marketplace.sites', [
            'category' => $category->id,
        ]))
            ->assertOk()
            ->assertSee('matching-site.test')
            ->assertDontSee('uncategorized-site.test');
    }

    public function test_sites_can_be_filtered_by_price_and_revenue(): void
    {
        $this->actingAs($this->admin());

        Site::factory()->create([
            'domain' => 'expensive-site.test',
            'price' => 1_000_000,
            'monthly_revenue' => 90_000,
        ]);

        Site::factory()->create([
            'domain' => 'cheap-site.test',
            'price' => 10_000,
            'monthly_revenue' => 1_000,
        ]);

        $this->get(route('platform.marketplace.sites', [
            'filter' => [
                'price' => ['min' => 500_000],
                'monthly_revenue' => ['min' => 50_000],
            ],
        ]))
            ->assertOk()
            ->assertSee('expensive-site.test')
            ->assertDontSee('cheap-site.test');
    }

    public function test_sites_can_be_filtered_by_verification(): void
    {
        $this->actingAs($this->admin());

        Site::factory()->verified()->create(['domain' => 'alpha-verified.test']);
        Site::factory()->create(['domain' => 'beta-unverified.test']);

        $this->get(route('platform.marketplace.sites', [
            'filter' => ['verified' => ['1']],
        ]))
            ->assertOk()
            ->assertSee('alpha-verified.test')
            ->assertDontSee('beta-unverified.test');
    }

    public function test_admin_can_update_commercial_fields_and_categories(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->create(['domain' => 'old-domain.test']);
        $category = Category::factory()->create();

        $this->post(route('platform.marketplace.sites.edit', [$site, 'save']), [
            'site' => [
                'title' => 'Обновлённое объявление',
                'domain' => 'new-domain.test',
                'description' => str_repeat('Описание объявления. ', 5),
                'price' => '250000.50',
                'monthly_revenue' => '15000.25',
                'has_trademark' => '0',
                'categories' => [$category->id],
            ],
        ])->assertRedirect();

        $site->refresh();

        $this->assertSame('Обновлённое объявление', $site->title);
        $this->assertSame('new-domain.test', $site->domain);
        $this->assertSame('250000.50', $site->price);
        $this->assertSame('15000.25', $site->monthly_revenue);
        $this->assertFalse($site->has_trademark);
        $this->assertTrue($site->categories->contains($category));
    }

    public function test_site_form_validates_title_domain_description_and_price(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->create();

        $this->post(route('platform.marketplace.sites.edit', [$site, 'save']), [
            'site' => [
                'title' => 'ab',
                'domain' => 'not a domain',
                'description' => 'short',
                'price' => '-1',
                'monthly_revenue' => '-5',
                'categories' => [],
            ],
        ])->assertSessionHasErrors([
            'site.title',
            'site.domain',
            'site.description',
            'site.price',
            'site.monthly_revenue',
            'site.categories',
        ]);
    }

    public function test_domain_must_be_unique_among_active_and_inactive_sites(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->create(['domain' => 'first-site.test']);
        Site::factory()->create(['domain' => 'taken-site.test']);
        Site::factory()->withdrawn()->create(['domain' => 'withdrawn-site.test']);

        $payload = [
            'title' => $site->title,
            'description' => $site->description,
            'price' => $site->price,
            'monthly_revenue' => $site->monthly_revenue,
            'categories' => [Category::factory()->create()->id],
        ];

        $this->post(route('platform.marketplace.sites.edit', [$site, 'save']), [
            'site' => $payload + ['domain' => 'taken-site.test'],
        ])->assertSessionHasErrors('site.domain');

        $this->post(route('platform.marketplace.sites.edit', [$site, 'save']), [
            'site' => $payload + ['domain' => 'withdrawn-site.test'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('withdrawn-site.test', $site->refresh()->domain);
    }

    public function test_admin_can_toggle_verification(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->create(['verified' => false]);
        $category = Category::factory()->create();

        $this->post(route('platform.marketplace.sites.edit', [$site, 'save']), [
            'site' => [
                'title' => $site->title,
                'domain' => $site->domain,
                'description' => $site->description,
                'price' => $site->price,
                'monthly_revenue' => $site->monthly_revenue,
                'verified' => '1',
                'categories' => [$category->id],
            ],
        ])->assertRedirect();

        $this->assertTrue($site->refresh()->verified);

        $this->post(route('platform.marketplace.sites.edit', [$site, 'save']), [
            'site' => [
                'title' => $site->title,
                'domain' => $site->domain,
                'description' => $site->description,
                'price' => $site->price,
                'monthly_revenue' => $site->monthly_revenue,
                'verified' => '0',
                'categories' => [$category->id],
            ],
        ])->assertRedirect();

        $this->assertFalse($site->refresh()->verified);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->sold()->create();

        $this->post(route('platform.marketplace.sites.edit', [$site, 'changeStatus']), [
            'site' => ['status' => ListingStatus::Active->value],
        ])->assertRedirect();

        $this->assertSame(ListingStatus::Sold, $site->refresh()->status);
    }

    public function test_service_fields_cannot_be_changed_through_the_form(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->create([
            'paid_until' => Carbon::parse('2030-01-31'),
            'verified' => false,
        ]);
        $category = Category::factory()->create();

        $verificationFilename = $site->verification_filename;

        $this->post(route('platform.marketplace.sites.edit', [$site, 'save']), [
            'site' => [
                'title' => $site->title,
                'domain' => $site->domain,
                'description' => $site->description,
                'price' => $site->price,
                'monthly_revenue' => $site->monthly_revenue,
                'categories' => [$category->id],
                'verification_filename' => 'hacked.txt',
                'paid_until' => '2020-01-01',
                'sold_at' => '2020-01-01 00:00:00',
                'user_id' => 999,
            ],
        ])->assertRedirect();

        $site->refresh();

        $this->assertSame($verificationFilename, $site->verification_filename);
        $this->assertSame('2030-01-31', $site->paid_until->toDateString());
        $this->assertNull($site->sold_at);
    }

    public function test_site_card_shows_commercial_fields_and_related_data(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->create([
            'title' => 'Карточка объявления',
            'domain' => 'card-site.test',
            'paid_until' => Carbon::parse('2030-05-31'),
        ]);

        $category = Category::factory()->create(['name' => 'Карточная тематика']);
        $site->categories()->attach($category);

        SubscriptionPayment::factory()->for($site)->paid()->create([
            'amount' => 4321,
        ]);

        Conversation::factory()
            ->forSite($site, User::factory()->create(['name' => 'Карточный покупатель']))
            ->create();

        $this->get(route('platform.marketplace.sites.edit', $site))
            ->assertOk()
            ->assertSee('card-site.test')
            ->assertSee('Карточная тематика')
            ->assertSee('31.05.2030')
            ->assertSee('4 321.00')
            ->assertSee('Карточный покупатель');
    }
}
