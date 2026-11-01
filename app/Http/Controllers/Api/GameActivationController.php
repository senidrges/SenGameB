<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameStatus;
use App\Events\GameChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\LoveGameResource;
use App\Models\LoveGame;
use App\Notifications\GameActivated;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class GameActivationController extends Controller
{
    /**
     * Open the game to the players. Activating a completed game starts a new
     * round: the previous choices are cleared so the surprises are hidden
     * again and every player picks anew.
     */
    public function store(LoveGame $game): LoveGameResource
    {
        Gate::authorize('update', $game);

        DB::transaction(function () use ($game): void {
            if ($game->status === GameStatus::Completed) {
                $game->choices()->delete();
            }

            $game->update(['status' => GameStatus::Active]);
        });

        $game->load(LoveGame::DETAIL_RELATIONS);

        Realtime::send(GameChanged::updated($game));
        Notification::send($game->players()->get(), new GameActivated($game));

        return LoveGameResource::make($game);
    }
}
