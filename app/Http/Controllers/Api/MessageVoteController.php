<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageType;
use App\Events\MessageUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MessageVoteController extends Controller
{
    /**
     * Replace the user's answers to a poll or an event.
     */
    public function store(Request $request, Message $message): MessageResource
    {
        Gate::authorize('sendMessage', $message->conversation);
        abort_if($message->is_deleted, 422, 'Ce message a été supprimé.');
        abort_unless($message->message_type->acceptsVotes(), 422, 'On ne peut pas voter sur ce message.');

        $allowsMultiple = $message->message_type === MessageType::Poll && ($message->payload['allow_multiple'] ?? false);

        $validated = $request->validate([
            'option_ids' => ['present', 'array', $allowsMultiple ? 'max:12' : 'max:1'],
            'option_ids.*' => ['string', 'distinct', Rule::in($message->voteOptions())],
        ]);

        $message->setVotes($request->user()->id, $validated['option_ids']);

        Realtime::send(new MessageUpdated($message));

        return MessageResource::make($message);
    }
}
