<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicSiteResource;
use App\Models\Site;
use App\Services\Seo\ListingImageGenerator;
use App\Support\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Серверная оболочка SPA с готовыми метатегами.
 *
 * Поисковики исполняют JavaScript, а VK и Telegram — нет, поэтому заголовок,
 * описание, canonical, Open Graph и JSON-LD отдаются в исходном HTML.
 * Данные лота дополнительно кладутся в window.__INITIAL_STATE__, чтобы SPA
 * отрисовала страницу без повторного запроса.
 */
class ListingPageController extends Controller
{
    public function __construct(private readonly ListingImageGenerator $images) {}

    public function __invoke(Request $request, string $slug): View|RedirectResponse
    {
        $siteId = (int) Str::before($slug, '-');

        abort_if($siteId <= 0, 404);

        $site = Site::query()
            ->publiclyVisible()
            ->with(['categories', 'owner'])
            ->when($request->user() !== null, fn (Builder $query) => $query->withExists([
                'favorites as is_favorite' => fn (Builder $favoritesQuery) => $favoritesQuery->where('user_id', $request->user()->id),
            ]))
            ->findOrFail($siteId);

        // Слаг в URL не совпадает с названием — постоянный редирект на канонический адрес.
        if ($slug !== $this->canonicalSlug($site)) {
            return redirect()->to($site->canonicalPath(), 301);
        }

        $categories = $site->categories->pluck('name')->all();
        $imageUrl = $this->images->urlFor($site);

        return view('app', [
            'meta' => SeoMeta::forListing($site, $categories, $imageUrl)->toArray(),
            'initialState' => [
                'listing' => (new PublicSiteResource($site))->resolve($request),
            ],
        ]);
    }

    private function canonicalSlug(Site $site): string
    {
        return $site->getKey().'-'.$site->slug();
    }
}
