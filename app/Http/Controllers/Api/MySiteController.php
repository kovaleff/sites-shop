<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use Illuminate\Database\Eloquent\Builder;
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

        $sites = $user->sites()
            ->with('categories')
            ->withCount(['messages as unread_messages_count' => function (Builder $query) use ($user): void {
                $query->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at');
            }])
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

        $site = $request->user()->sites()->create($this->attributes($data));

        $site->categories()->sync($data['categories']);

        return (new SiteResource($site->load('categories')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Карточка объявления с историей платежей (FR-004, FR-024).
     */
    public function show(Request $request, int $site): SiteResource
    {
        $model = $request->user()->sites()
            ->with(['categories', 'subscriptionPayments'])
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

        return new SiteResource($model->load('categories'));
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
