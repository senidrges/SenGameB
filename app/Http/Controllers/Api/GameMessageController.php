<?php

namespace App\Http\Controllers\Api;

use App\Actions\SendGameMessage;
use App\Events\GameMessageChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameMessageResource;
use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GameMessageController extends Controller
{
    public function index(LoveGame $game): AnonymousResourceCollection
    {
        Gate::authorize('view', $game);

        return GameMessageResource::collection($game->messages()->with(['sender', 'replyTo.sender'])->get());
    }

    public function store(Request $request, LoveGame $game, SendGameMessage $sendGameMessage): JsonResponse
    {
        Gate::authorize('view', $game);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'uuid', Rule::exists('game_messages', 'id')->where('game_id', $game->id)],
        ]);

        $message = $sendGameMessage->handle($game, $request->user(), trim($validated['content']), $validated['reply_to_id'] ?? null);

        return GameMessageResource::make($message)->response()->setStatusCode(201);
    }

    public function update(Request $request, GameMessage $gameMessage): GameMessageResource
    {
        Gate::authorize('update', $gameMessage);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $gameMessage->forceFill(['content' => trim($validated['content']), 'edited_at' => now()])->save();

        Realtime::send(new GameMessageChanged($gameMessage));

        return GameMessageResource::make($gameMessage);
    }

    /**
     * Delete a message for everyone. It stays in the chat as "deleted".
     */
    public function destroy(GameMessage $gameMessage): GameMessageResource
    {
        Gate::authorize('delete', $gameMessage);

        $gameMessage->markDeletedForEveryone();

        Realtime::send(new GameMessageChanged($gameMessage));

        return GameMessageResource::make($gameMessage);
    }
}
