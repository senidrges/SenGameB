<?php

namespace App\Http\Controllers\Api;

use App\Events\GameMessageChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameMessageResource;
use App\Models\GameMessage;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GameMessageReactionController extends Controller
{
    /**
     * Toggle the user's reaction on a game chat message.
     */
    public function store(Request $request, GameMessage $gameMessage): GameMessageResource
    {
        Gate::authorize('view', $gameMessage);

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $gameMessage->toggleReaction($validated['emoji'], $request->user()->id);

        Realtime::send(new GameMessageChanged($gameMessage));

        return GameMessageResource::make($gameMessage);
    }
}
