<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    /**
     * Ответ в диалоге (FR-018, FR-019).
     */
    public function store(StoreMessageRequest $request, int $conversation): JsonResponse
    {
        $user = $request->user();

        $model = Conversation::query()
            ->forParticipant($user)
            ->with('site')
            ->findOrFail($conversation);

        try {
            $message = $model->postMessage($user, $request->validated()['body']);
        } catch (DomainException) {
            throw ValidationException::withMessages([
                'body' => 'Отправка сообщения в этот диалог недоступна.',
            ]);
        }

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }
}
