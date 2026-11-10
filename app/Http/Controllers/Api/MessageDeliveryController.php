<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGameReceipts;
use App\Actions\UpdateGroupReceipts;
use App\Enums\ConversationType;
use App\Events\MessagesDelivered;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MessageDeliveryController extends Controller
{
    /**
     * The user's phone received every message sent to them so far: called
     * when the app opens, gets a message in realtime or a push notification.
     */
    public function store(Request $request, UpdateGameReceipts $gameReceipts, UpdateGroupReceipts $groupReceipts): Response
    {
        $user = $request->user();
        // Game chats too
        $gameReceipts->markDeliveredFor($user);

        $memberOf = fn (string $type) => DB::table('conversation_participants')
            ->join('conversations', 'conversations.id', '=', 'conversation_participants.conversation_id')
            ->where('conversation_participants.user_id', $user->id)
            ->whereNull('conversation_participants.left_at')
            ->where('conversations.type', $type)
            ->select('conversation_participants.conversation_id');

        // Groups: delivered once every member's phone got it
        $groupIds = Message::query()
            ->whereIn('conversation_id', $memberOf(ConversationType::Group->value))
            ->where('sender_id', '!=', $user->id)
            ->where('is_delivered', false)
            // Not already received by this phone, only waiting for the others
            ->whereNotExists(fn ($mine) => $mine
                ->from('conversation_participants')
                ->whereColumn('conversation_participants.conversation_id', 'messages.conversation_id')
                ->where('conversation_participants.user_id', $user->id)
                ->whereColumn('conversation_participants.last_delivered_at', '>=', 'messages.created_at'))
            ->distinct()
            ->pluck('conversation_id')
            ->all();
        $groupReceipts->markDelivered($user, $groupIds);

        $pending = Message::query()
            ->whereIn('conversation_id', $memberOf(ConversationType::Direct->value))
            ->where('sender_id', '!=', $user->id)
            ->where('is_delivered', false)
            ->get(['id', 'conversation_id', 'sender_id']);

        if ($pending->isEmpty()) {
            return response()->noContent();
        }

        Message::query()->whereKey($pending->modelKeys())->update(['is_delivered' => true]);

        $pending->groupBy('conversation_id')->each(fn (Collection $messages, string $conversationId) => Realtime::send(
            new MessagesDelivered($conversationId, $user->id, $messages->pluck('sender_id')->unique()->values()->all()),
        ));

        return response()->noContent();
    }
}
