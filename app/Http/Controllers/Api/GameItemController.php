<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameStatus;
use App\Events\GameChanged;
use App\Events\GameItemsReplaced;
use App\Http\Controllers\Controller;
use App\Http\Requests\GameItemRules;
use App\Http\Resources\GameItemResource;
use App\Models\LoveGame;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GameItemController extends Controller
{
    /**
     * Replace every item of the game. Choices made on the old items no longer
     * mean anything: they are cleared and the game waits for activation.
     */
    public function update(Request $request, LoveGame $game): AnonymousResourceCollection
    {
        Gate::authorize('update', $game);

        $validated = $request->validate(GameItemRules::rules());

        $roundCleared = DB::transaction(function () use ($game, $validated): bool {
            $game->items()->delete();
            $game->items()->createMany($validated['items']);

            $roundCleared = $game->choices()->delete() > 0;
            if ($roundCleared) {
                $game->status = GameStatus::Waiting;
            }
            $game->touch();

            return $roundCleared;
        });

        $game->load('items');

        Realtime::send(new GameItemsReplaced($game));
        if ($roundCleared) {
            Realtime::send(GameChanged::updated($game));
        }

        return GameItemResource::collection($game->items);
    }
}
