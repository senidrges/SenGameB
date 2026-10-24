<?php

namespace App\Http\Controllers\Api;

use App\Events\MessagesRead;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConversationReadController extends Controller
{
    /**
     * Mark the messages the other participants sent as read.
     */
    public function store(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();

        $updated = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($updated > 0) {
            Realtime::send(new MessagesRead($conversation->id, $user->id));
        }

        return response()->noContent();
    }
}
