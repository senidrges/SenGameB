<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Réorganiser" used to reactivate a completed game without clearing the
     * choices: every player had already chosen, so the game stayed active
     * for good and the players could no longer see the surprises. Those
     * games are completed again.
     */
    public function up(): void
    {
        DB::table('love_games')
            ->where('status', 'active')
            ->whereExists(fn (Builder $players) => $players
                ->from('game_participants')
                ->whereColumn('game_participants.game_id', 'love_games.id')
                ->where('game_participants.role', 'player'))
            ->whereNotExists(fn (Builder $playersWithoutChoice) => $playersWithoutChoice
                ->from('game_participants')
                ->whereColumn('game_participants.game_id', 'love_games.id')
                ->where('game_participants.role', 'player')
                ->whereNotExists(fn (Builder $choices) => $choices
                    ->from('game_choices')
                    ->whereColumn('game_choices.game_id', 'game_participants.game_id')
                    ->whereColumn('game_choices.user_id', 'game_participants.user_id')))
            ->update(['status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
