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

class ConversationTypingController extends Controller
{
    /**
     * Tell the others the user is writing. The app calls it every few
     * seconds while the user types. Nothing is sent when a block is in place.
     */
    public function store(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();

        $recipientIds = $conversation->participants()->get()
            ->reject(fn (User $participant) => $participant->is($user) || $user->hasBlockWith($participant))
            ->modelKeys();

        if ($recipientIds !== []) {
            Realtime::send(new UserTyping($conversation->id, $user->id, array_values($recipientIds)));
        }

        return response()->noContent();
    }
}
