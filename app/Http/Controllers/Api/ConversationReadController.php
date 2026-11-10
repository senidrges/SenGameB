<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGroupReceipts;
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
     * Mark the messages the other participants sent as read (and so
     * delivered). In a group, a message turns read once every member read it.
     */
    public function store(Request $request, Conversation $conversation, UpdateGroupReceipts $groupReceipts): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();

        if ($conversation->isGroup()) {
            if ($conversation->hasMember($user)) {
                $groupReceipts->markRead($conversation, $user);
            }

            return response()->noContent();
        }

        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        $unread = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false);

        $senderIds = (clone $unread)->distinct()->pluck('sender_id')->all();

        if ($senderIds === []) {
            return response()->noContent();
        }

        $unread->update(['is_read' => true, 'is_delivered' => true]);

        Realtime::send(new MessagesRead($conversation->id, $user->id, $senderIds));

        return response()->noContent();
    }
}
