<?php

namespace App\Http\Controllers\Api;

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
     * Replace every item of the game.
     */
    public function update(Request $request, LoveGame $game): AnonymousResourceCollection
    {
        Gate::authorize('update', $game);

        $validated = $request->validate(GameItemRules::rules());

        DB::transaction(function () use ($game, $validated): void {
            $game->items()->delete();
            $game->items()->createMany($validated['items']);
            $game->touch();
        });

        $game->load('items');

        Realtime::send(new GameItemsReplaced($game));

        return GameItemResource::collection($game->items);
    }
}
