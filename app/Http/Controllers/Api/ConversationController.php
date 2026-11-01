<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    /**
     * Conversations of the user, most recent activity first. A conversation
     * the user deleted comes back only with a message sent after that.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(Conversation::participantsWithBlockStateFor($user))
            ->withCount(['messages as unread_count' => fn ($messages) => $messages
                ->visibleTo($user)
                ->where('is_read', false)
                ->where('sender_id', '!=', $user->id)])
            ->orderByDesc('conversations.updated_at')
            ->get();

        Conversation::loadLatestMessageVisibleTo($conversations, $user);

        $conversations = $conversations
            ->filter(fn (Conversation $conversation) => $conversation->pivot->deleted_at === null
                || $conversation->latestMessage !== null)
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

        $conversation->load(Conversation::participantsWithBlockStateFor($user));
        Conversation::loadLatestMessageVisibleTo($conversation->newCollection([$conversation]), $user);

        return ConversationResource::make($conversation)
            ->response()
            ->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * A single conversation, with whether the user can still write in it.
     */
    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $conversation->load(Conversation::participantsWithBlockStateFor($user));
        Conversation::loadLatestMessageVisibleTo($conversation->newCollection([$conversation]), $user);

        return ConversationResource::make($conversation);
    }

    /**
     * Delete the conversation for the user only. Nothing is erased: its
     * messages stay in the database (and for the other participant), they
     * are just hidden from the user. It shows again with the next message.
     */
    public function destroy(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $conversation->participants()->updateExistingPivot($request->user()->id, ['deleted_at' => now()]);

        return response()->noContent();
    }
}
