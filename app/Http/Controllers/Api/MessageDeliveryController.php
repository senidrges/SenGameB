<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGameReceipts;
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
    public function store(Request $request, UpdateGameReceipts $gameReceipts): Response
    {
        $user = $request->user();
        // Game chats too
        $gameReceipts->markDeliveredFor($user);

        $pending = Message::query()
            ->whereIn('conversation_id', DB::table('conversation_participants')->where('user_id', $user->id)->select('conversation_id'))
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
