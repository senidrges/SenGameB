<?php

namespace App\Http\Controllers\Api;

use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConversationTypingController extends Controller
{
    /**
     * Tell the others the user is writing or recording a voice message. The
     * app calls it every few seconds meanwhile. Nothing is sent when a block
     * is in place.
     */
    public function store(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'activity' => ['nullable', Rule::in(UserTyping::ACTIVITIES)],
        ]);

        $user = $request->user();

        abort_unless($conversation->hasMember($user), 403);

        // Blocks do not apply in groups, like WhatsApp
        $recipientIds = $conversation->members()->get()
            ->reject(fn (User $participant) => $participant->is($user)
                || (! $conversation->isGroup() && $user->hasBlockWith($participant)))
            ->modelKeys();

        if ($recipientIds !== []) {
            Realtime::send(new UserTyping(
                $conversation->id,
                $user->id,
                array_values($recipientIds),
                $validated['activity'] ?? UserTyping::TYPING,
            ));
        }

        return response()->noContent();
    }
}
