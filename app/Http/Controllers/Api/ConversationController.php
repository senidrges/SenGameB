<?php

namespace App\Http\Controllers\Api;

use App\Actions\FinishCall;
use App\Actions\PurgeExpiredMessages;
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
    public function index(Request $request, FinishCall $finishCall, PurgeExpiredMessages $purge): AnonymousResourceCollection
    {
        $user = $request->user();
        $purge->handle();
        // Abandoned calls show up as missed in the list
        $finishCall->finishExpiredFor($user);

        $conversations = $user->conversations()
            ->with(Conversation::participantsWithBlockStateFor($user))
            ->withCount(['messages as unread_count' => fn ($messages) => $messages
                ->visibleTo($user)
                // In a group `is_read` means read by every member: what the
                // user read is on their own row
                ->where('is_read', false)
                ->where('sender_id', '!=', $user->id)
                // Group notices ("Alice changed the name") make no badge
                ->where('message_type', '!=', 'system')
                ->whereNotExists(fn ($mine) => $mine
                    ->from('conversation_participants')
                    ->whereColumn('conversation_participants.conversation_id', 'messages.conversation_id')
                    ->where('conversation_participants.user_id', $user->id)
                    ->whereColumn('conversation_participants.last_read_at', '>=', 'messages.created_at')),
                'messages as unread_mentions_count' => fn ($messages) => $messages
                    ->visibleTo($user)
                    ->where('is_read', false)
                    ->whereHas('mentions', fn ($mentioned) => $mentioned->whereKey($user->id))
                    ->whereNotExists(fn ($mine) => $mine
                        ->from('conversation_participants')
                        ->whereColumn('conversation_participants.conversation_id', 'messages.conversation_id')
                        ->where('conversation_participants.user_id', $user->id)
                        ->whereColumn('conversation_participants.last_read_at', '>=', 'messages.created_at')),
            ])
            ->orderByDesc('conversations.updated_at')
            ->get();

        Conversation::loadLatestMessageVisibleTo($conversations, $user);

        $conversations = $conversations
            ->filter(fn (Conversation $conversation) => $conversation->pivot->deleted_at === null
                || $conversation->latestMessage !== null)
            ->filter(fn (Conversation $conversation) => $conversation->isGroup() || $conversation->participants
                ->contains(fn (User $participant) => ! $participant->is($user) && $participant->hasProfile()))
            ->values();

        return ConversationResource::collection($conversations);
    }

    /**
     * Open the one-to-one conversation with another user, creating it if
     * needed. Like on WhatsApp, a new conversation needs the number of the
     * other user in one of the two phones, or an accepted invitation.
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

        abort_unless(
            $user->canReach($other),
            403,
            "Vous n'avez pas le numéro de cette personne : envoyez-lui d'abord une invitation.",
        );

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

        return ConversationResource::make($conversation)->withMembers();
    }

    /**
     * Delete the conversation for the user only. Nothing is erased: its
     * messages stay in the database (and for the other participant), they
     * are just hidden from the user. It shows again with the next message.
     */
    public function destroy(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        // Like WhatsApp, a group is deleted once left
        abort_if(
            $conversation->isGroup() && $conversation->hasMember($request->user()),
            422,
            "Quittez d'abord le groupe pour le supprimer.",
        );

        $conversation->participants()->updateExistingPivot($request->user()->id, ['deleted_at' => now()]);

        return response()->noContent();
    }
}
