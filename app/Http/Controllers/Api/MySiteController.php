<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Jobs\VerifySiteDomain;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MySiteController extends Controller
{
    /**
     * Объявления текущего пользователя (FR-003).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $onlyHidden = $request->boolean('hidden');

        $sites = $this->withMetrics($user->sites())
            ->with('categories')
            ->withCount([
                'favorites as favorites_count',
                'messages as messages_count',
                'messages as unread_messages_count' => function (Builder $query) use ($user): void {
                    $query->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at');
                },
            ])
            ->when($onlyHidden, fn (Builder $query) => $query->hiddenFromCabinet())
            ->when(! $onlyHidden, fn (Builder $query) => $query->visibleInCabinet())
            ->newestFirst()
            ->paginate(25);

        return response()->json([
            'data' => SiteResource::collection($sites->getCollection())->resolve($request),
            'meta' => $this->paginationMeta($sites),
        ]);
    }

    /**
     * Создание объявления (FR-007–FR-011).
     */
    public function store(StoreSiteRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->ensureListingLimit($request->user());

        $site = $request->user()->sites()->create($this->attributes($data));

        $site->categories()->sync($data['categories']);

        VerifySiteDomain::dispatch($site->getKey())->afterCommit();

        return (new SiteResource($site->load('categories')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Карточка объявления с историей платежей (FR-004, FR-024).
     */
    public function show(Request $request, int $site): SiteResource
    {
        $model = $this->withMetrics($request->user()->sites())
            ->with(['categories', 'subscriptionPayments'])
            ->withCount([
                'favorites as favorites_count',
                'messages as messages_count',
                'messages as unread_messages_count' => function (Builder $query) use ($request): void {
                    $query->where('sender_id', '!=', $request->user()->id)
                        ->whereNull('read_at');
                },
            ])
            ->findOrFail($site);

        return new SiteResource($model);
    }

    /**
     * Редактирование объявления; проданное объявление недоступно (FR-012).
     */
    public function update(UpdateSiteRequest $request, int $site): SiteResource
    {
        $model = $request->user()->sites()->findOrFail($site);

        if ($model->status === ListingStatus::Sold) {
            throw ValidationException::withMessages([
                'status' => 'Проданное объявление нельзя редактировать.',
            ]);
        }

        $data = $request->validated();

        $model->update($this->attributes($data));
        $model->categories()->sync($data['categories']);

        if ($model->wasChanged('domain')) {
            $model->resetDomainVerification();
            VerifySiteDomain::dispatch($model->getKey())->afterCommit();
        } elseif (! $model->verified) {
            VerifySiteDomain::dispatch($model->getKey())->afterCommit();
        } elseif ($model->moderation_status === ModerationStatus::Rejected) {
            $model->setModeration(ModerationStatus::Pending, 'Объявление отредактировано продавцом.');
        }

        return new SiteResource($model->load('categories'));
    }

    /**
     * Агрегаты просмотров, избранного и сообщений для карточки кабинета.
     *
     * @param  HasMany<Site, User>  $query
     * @return HasMany<Site, User>
     */
    private function withMetrics(HasMany $query): HasMany
    {
        return $query
            ->withSum(
                ['views as views_today_sum' => fn (Builder $views) => $views->where('date', today()->toDateString())],
                'count',
            )
            ->withSum(
                ['views as views_week_sum' => fn (Builder $views) => $views->where('date', '>=', today()->subDays(6)->toDateString())],
                'count',
            )
            ->withSum(
                ['views as views_month_sum' => fn (Builder $views) => $views->where('date', '>=', today()->subDays(29)->toDateString())],
                'count',
            )
            ->withSum('views as views_total_sum', 'count');
    }

    /**
     * Ограничение числа объявлений в активных статусах на одного продавца.
     */
    private function ensureListingLimit(User $user): void
    {
        $limit = (int) config('sites.max_active_per_user');

        if ($limit <= 0) {
            return;
        }

        $activeListings = $user->sites()
            ->whereIn('status', [ListingStatus::Active->value, ListingStatus::Inactive->value])
            ->count();

        if ($activeListings >= $limit) {
            throw ValidationException::withMessages([
                'title' => "Достигнут лимит объявлений ({$limit}). Снимите с продажи лишние объявления.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'domain' => $data['domain'],
            'description' => $data['description'],
            'price' => $data['price'],
            'monthly_revenue' => $data['monthlyRevenue'],
            'has_trademark' => (bool) ($data['hasTrademark'] ?? false),
        ];
    }
}
