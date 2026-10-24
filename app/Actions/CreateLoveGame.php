<?php

namespace App\Actions;

use App\Enums\GameRole;
use App\Events\GameChanged;
use App\Models\LoveGame;
use App\Models\User;
use App\Notifications\GameCreated;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CreateLoveGame
{
    /**
     * Create a game with its players and items, then notify the players.
     *
     * @param  list<string>  $playerIds
     * @param  list<array{number: int, content: string}>  $items
     */
    public function handle(User $organizer, string $title, array $playerIds, array $items, ?string $conversationId = null): LoveGame
    {
        $game = DB::transaction(function () use ($organizer, $title, $playerIds, $items, $conversationId): LoveGame {
            $game = LoveGame::create([
                'organizer_id' => $organizer->id,
                'conversation_id' => $conversationId,
                'title' => $title,
            ]);

            $game->participants()->attach([
                $organizer->id => ['role' => GameRole::Organizer->value],
                ...array_fill_keys($playerIds, ['role' => GameRole::Player->value]),
            ]);

            $game->items()->createMany($items);

            return $game;
        });

        $game->load(LoveGame::DETAIL_RELATIONS);

        Realtime::send(GameChanged::created($game));

        Notification::send(
            $game->participants->reject(fn (User $participant) => $participant->is($organizer)),
            new GameCreated($game),
        );

        return $game;
    }
}
