<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageType;
use App\Events\GameMessageChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameMessageResource;
use App\Models\GameMessage;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GameMessageVoteController extends Controller
{
    /**
     * Replace the user's answers to a poll or an event of the game chat.
     */
    public function store(Request $request, GameMessage $gameMessage): GameMessageResource
    {
        Gate::authorize('view', $gameMessage);
        abort_if($gameMessage->is_deleted, 422, 'Ce message a été supprimé.');
        abort_unless($gameMessage->message_type->acceptsVotes(), 422, 'On ne peut pas voter sur ce message.');

        $allowsMultiple = $gameMessage->message_type === MessageType::Poll && ($gameMessage->payload['allow_multiple'] ?? false);

        $validated = $request->validate([
            'option_ids' => ['present', 'array', $allowsMultiple ? 'max:12' : 'max:1'],
            'option_ids.*' => ['string', 'distinct', Rule::in($gameMessage->voteOptions())],
        ]);

        $gameMessage->setVotes($request->user()->id, $validated['option_ids']);

        Realtime::send(new GameMessageChanged($gameMessage));

        return GameMessageResource::make($gameMessage);
    }
}
