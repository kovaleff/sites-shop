<?php

namespace Tests\Feature\Catalog;

use App\Models\Site;
use App\Models\SiteView;
use App\Models\User;
use App\Services\Metrics\ListingViewRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingViewsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-11-03 12:00:00');
    }

    public function test_listing_page_records_a_view(): void
    {
        $site = Site::factory()->paid()->create();

        $this->withHeader('User-Agent', self::BROWSER_USER_AGENT)
            ->get($site->canonicalPath())
            ->assertOk();

        $this->assertDatabaseHas('site_views', [
            'site_id' => $site->id,
            'date' => '2026-11-03',
            'count' => 1,
        ]);
    }

    public function test_listing_api_records_a_view(): void
    {
        $site = Site::factory()->paid()->create();

        $this->withHeader('User-Agent', self::BROWSER_USER_AGENT)
            ->getJson('/api/v1/listings/'.$site->id)
            ->assertOk();

        $this->assertDatabaseHas('site_views', [
            'site_id' => $site->id,
            'date' => '2026-11-03',
            'count' => 1,
        ]);
    }

    public function test_repeated_visits_from_one_visitor_are_counted_once_per_day(): void
    {
        $site = Site::factory()->paid()->create();

        $session = app('session.store');
        $session->setId('view-test-session');

        $request = Request::create(
            $site->canonicalPath(),
            'GET',
            server: ['HTTP_USER_AGENT' => self::BROWSER_USER_AGENT],
        );
        $request->setLaravelSession($session);

        $recorder = app(ListingViewRecorder::class);
        $recorder->record($request, $site);
        $recorder->record($request, $site);

        $this->assertDatabaseHas('site_views', [
            'site_id' => $site->id,
            'date' => '2026-11-03',
            'count' => 1,
        ]);
    }

    public function test_bot_visits_are_not_counted(): void
    {
        $site = Site::factory()->paid()->create();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->get($site->canonicalPath())
            ->assertOk();

        $this->assertDatabaseCount('site_views', 0);
    }

    public function test_owner_visits_are_not_counted(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->paid()->create();

        $this->actingAs($owner)
            ->withHeader('User-Agent', self::BROWSER_USER_AGENT)
            ->get($site->canonicalPath())
            ->assertOk();

        $this->assertDatabaseCount('site_views', 0);
    }

    public function test_view_is_counted_again_on_another_day(): void
    {
        $site = Site::factory()->paid()->create();
        SiteView::factory()->create([
            'site_id' => $site->id,
            'date' => '2026-11-02',
            'count' => 4,
        ]);

        $this->withHeader('User-Agent', self::BROWSER_USER_AGENT)
            ->get($site->canonicalPath())
            ->assertOk();

        $this->assertDatabaseHas('site_views', [
            'site_id' => $site->id,
            'date' => '2026-11-02',
            'count' => 4,
        ]);
        $this->assertDatabaseHas('site_views', [
            'site_id' => $site->id,
            'date' => '2026-11-03',
            'count' => 1,
        ]);
    }
}
