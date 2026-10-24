<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    /**
     * Conversations of the user, most recent activity first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with([...Conversation::participantsWithBlockStateFor($user), 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($messages) => $messages
                ->where('is_read', false)
                ->where('sender_id', '!=', $user->id)])
            ->orderByDesc('conversations.updated_at')
            ->get()
            ->filter(fn (Conversation $conversation) => $conversation->participants
                ->contains(fn (User $participant) => ! $participant->is($user) && $participant->hasProfile()))
            ->values();

        return ConversationResource::collection($conversations);
    }

    /**
     * Open the one-to-one conversation with another user, creating it if needed.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => [
                'required',
                'uuid',
                Rule::notIn([$user->id]),
                Rule::exists('users', 'id')->whereNotNull('username'),
            ],
        ]);

        $other = User::findOrFail($validated['user_id']);

        $conversation = DB::transaction(function () use ($user, $other): Conversation {
            $conversation = Conversation::createOrFirst(['direct_key' => Conversation::directKeyFor($user, $other)]);
            $conversation->participants()->syncWithoutDetaching([$user->id, $other->id]);

            return $conversation;
        });

        return ConversationResource::make($conversation->load([...Conversation::participantsWithBlockStateFor($user), 'latestMessage']))
            ->response()
            ->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * A single conversation, with whether the user can still write in it.
     */
    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        return ConversationResource::make($conversation->load([
            ...Conversation::participantsWithBlockStateFor($request->user()),
            'latestMessage',
        ]));
    }
}
