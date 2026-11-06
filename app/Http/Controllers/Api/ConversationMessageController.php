<?php

namespace App\Http\Controllers\Api;

use App\Actions\FinishCall;
use App\Actions\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ConversationMessageController extends Controller
{
    /**
     * A page of messages, oldest first. Pass `before` to load older ones.
     * Messages the user deleted "for me", or that predate their deletion of
     * the conversation, are left out.
     */
    public function index(Request $request, Conversation $conversation, FinishCall $finishCall): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        // Abandoned calls get their trace before the messages are listed
        $finishCall->finishExpiredFor($request->user());

        $validated = $request->validate([
            'before' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $conversation->messages()
            ->visibleTo($request->user())
            ->with(['sender', 'replyTo.sender'])
            ->when($request->date('before'), fn ($query, $before) => $query->where('created_at', '<', $before))
            ->latest()
            ->orderByDesc('id')
            ->limit($validated['limit'] ?? 50)
            ->get()
            ->reverse()
            ->values();

        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation, SendMessage $sendMessage): JsonResponse
    {
        $message = $sendMessage->handle($conversation, $request->user(), $request->messageAttributes());

        return MessageResource::make($message)->response()->setStatusCode(201);
    }
}
