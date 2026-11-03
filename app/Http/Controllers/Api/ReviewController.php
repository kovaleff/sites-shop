<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\PublicSiteResource;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Models\Site;
use App\Models\User;
use App\Services\Reviews\ReviewEligibility;
use App\Services\Reviews\SellerReviews;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Отзывы продавца: только опубликованные модератором.
     */
    public function sellerReviews(Request $request, int $seller, SellerReviews $reviews): JsonResponse
    {
        abort_unless(User::query()->whereKey($seller)->exists(), 404);

        $paginator = $reviews->publishedFor($seller, (int) $request->query('page', 1));

        return response()->json([
            'data' => ReviewResource::collection($paginator->getCollection())->resolve($request),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    /**
     * Проданные лоты, по которым текущий покупатель ещё не оставил отзыв.
     */
    public function pending(Request $request, ReviewEligibility $eligibility): JsonResponse
    {
        $user = $request->user();

        $sites = Site::query()
            ->where('status', ListingStatus::Sold)
            ->whereHas('conversations', fn (Builder $query) => $query->where('buyer_id', $user->id))
            ->whereDoesntHave('reviews', fn (Builder $query) => $query->where('author_id', $user->id))
            ->with(['categories', 'owner'])
            ->latest('id')
            ->get()
            ->filter(fn (Site $site): bool => $eligibility->canReview($user, $site))
            ->values();

        return response()->json([
            'data' => PublicSiteResource::collection($sites)->resolve($request),
        ]);
    }

    /**
     * Отзывы, написанные текущим пользователем, включая отзывы на модерации.
     */
    public function mine(Request $request): JsonResponse
    {
        $reviews = $request->user()->writtenReviews()
            ->with(['site', 'seller'])
            ->latest('created_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => ReviewResource::collection($reviews)->resolve($request),
        ]);
    }

    /**
     * Оставить отзыв по проданному лоту.
     */
    public function store(
        StoreReviewRequest $request,
        int $site,
        ReviewEligibility $eligibility,
    ): JsonResponse {
        $siteModel = Site::query()->findOrFail($site);
        $user = $request->user();

        $failure = $eligibility->failureFor($user, $siteModel);

        if ($failure !== null) {
            throw ValidationException::withMessages(['review' => $failure]);
        }

        $review = Review::query()->create([
            'site_id' => $siteModel->getKey(),
            'author_id' => $user->getKey(),
            'seller_id' => $siteModel->user_id,
            'rating' => (int) $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        return (new ReviewResource($review->load(['site', 'author'])))
            ->response()
            ->setStatusCode(201);
    }
}
