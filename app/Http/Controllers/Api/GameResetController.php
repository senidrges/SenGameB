<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameStatus;
use App\Events\GameChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\LoveGameResource;
use App\Models\LoveGame;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GameResetController extends Controller
{
    /**
     * Clear every choice so the game can be played again.
     */
    public function store(LoveGame $game): LoveGameResource
    {
        Gate::authorize('update', $game);

        DB::transaction(function () use ($game): void {
            $game->choices()->delete();
            $game->status = GameStatus::Waiting;
            $game->touch();
        });

        $game->load(LoveGame::DETAIL_RELATIONS);

        Realtime::send(GameChanged::updated($game));

        return LoveGameResource::make($game);
    }
}
