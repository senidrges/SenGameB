<?php

namespace App\Http\Controllers\Api;

use App\Actions\SendGameMessage;
use App\Enums\MessageType;
use App\Events\GameMessageChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGameMessageRequest;
use App\Http\Resources\GameMessageResource;
use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class GameMessageController extends Controller
{
    public function index(LoveGame $game): AnonymousResourceCollection
    {
        Gate::authorize('view', $game);

        return GameMessageResource::collection($game->messages()->with(['sender', 'replyTo.sender'])->get());
    }

    /**
     * Text, voice message, photo, file, location, contact, poll or event.
     */
    public function store(StoreGameMessageRequest $request, LoveGame $game, SendGameMessage $sendGameMessage): JsonResponse
    {
        $message = $sendGameMessage->handle($game, $request->user(), Arr::except($request->messageAttributes(), ['mentions']));

        return GameMessageResource::make($message)->response()->setStatusCode(201);
    }

    public function update(Request $request, GameMessage $gameMessage): GameMessageResource
    {
        Gate::authorize('update', $gameMessage);
        abort_unless($gameMessage->message_type === MessageType::Text, 422, 'Seuls les messages texte peuvent être modifiés.');

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
