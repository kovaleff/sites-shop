<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Site;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    /**
     * Диалоги пользователя в обеих ролях (FR-016).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->forParticipant($user)
            ->with(['site', 'buyer', 'seller', 'latestMessage'])
            ->withCount(['messages as unread_messages_count' => function (Builder $query) use ($user): void {
                $query->where('sender_id', '!=', $user->id)->whereNull('read_at');
            }])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => ConversationResource::collection($conversations->getCollection())->resolve($request),
            'meta' => $this->paginationMeta($conversations),
        ]);
    }

    /**
     * История диалога; входящие сообщения помечаются прочитанными (FR-017).
     */
    public function show(Request $request, int $conversation): JsonResponse
    {
        $user = $request->user();

        $model = Conversation::query()
            ->forParticipant($user)
            ->with(['site', 'buyer', 'seller', 'latestMessage'])
            ->findOrFail($conversation);

        $model->markReadFor($user);

        $messages = $model->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => (new ConversationResource($model))->resolve($request),
            'messages' => MessageResource::collection($messages)->resolve($request),
        ]);
    }

    /**
     * Начать или продолжить переписку по объявлению (FR-021–FR-023).
     */
    public function store(StartConversationRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $site = Site::query()->find($data['siteId']);

        if ($site === null) {
            abort(404, 'Объявление не найдено.');
        }

        if (! $site->isPubliclyVisible()) {
            throw ValidationException::withMessages([
                'siteId' => 'Объявление недоступно для переписки.',
            ]);
        }

        try {
            $conversation = Conversation::startFor($site, $user);
            $conversation->postMessage($user, $data['body']);
        } catch (DomainException) {
            throw ValidationException::withMessages([
                'siteId' => 'Нельзя начать переписку по этому объявлению.',
            ]);
        }

        $conversation->load(['site', 'buyer', 'seller', 'latestMessage']);

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode(201);
    }
}
