<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Events\GameChanged;
use App\Models\LoveGame;
use App\Models\User;
use App\Notifications\GameChoiceMade;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;

class RecordGameChoice
{
    /**
     * Save the player's choice and complete the game once every player chose.
     *
     * The game row is locked so two players choosing at the same time cannot
     * both miss that the game is now complete.
     */
    public function handle(LoveGame $game, User $player, int $choiceNumber): LoveGame
    {
        DB::transaction(function () use ($game, $player, $choiceNumber): void {
            LoveGame::query()->whereKey($game->id)->lockForUpdate()->first();

            $game->choices()->updateOrCreate(
                ['user_id' => $player->id],
                ['choice_number' => $choiceNumber, 'chosen_at' => now()],
            );

            $playerIds = $game->players()->pluck('users.id');
            $everyPlayerChose = $playerIds->diff($game->choices()->pluck('user_id'))->isEmpty();

            $game->status = $everyPlayerChose ? GameStatus::Completed : GameStatus::Active;
            $game->touch();
        });

        $game->load(LoveGame::DETAIL_RELATIONS);

        Realtime::send(GameChanged::updated($game));

        $game->organizer->notify(new GameChoiceMade($game, $player));

        return $game;
    }
}
