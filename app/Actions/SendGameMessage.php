<?php

namespace App\Actions;

use App\Events\GameMessageChanged;
use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Models\User;
use App\Notifications\NewGameMessage;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendGameMessage
{
    /**
     * Post a message in the game chat and tell the other participants.
     *
     * @param  array{message_type?: string, content?: string|null, file_path?: string|null, file_name?: string|null, file_size?: int|null, payload?: array<string, mixed>|null, reply_to_id?: string|null}  $attributes
     */
    public function handle(LoveGame $game, User $sender, array $attributes): GameMessage
    {
        $message = DB::transaction(function () use ($game, $sender, $attributes): GameMessage {
            $message = $game->messages()->create([...$attributes, 'sender_id' => $sender->id]);
            $game->touch();

            return $message;
        });

        $message->setRelation('sender', $sender)->setRelation('game', $game)->load('replyTo.sender');

        Realtime::send(new GameMessageChanged($message, GameMessageChanged::CREATED));

        Notification::send(
            $game->participants()->whereKeyNot($sender->id)->get(),
            new NewGameMessage($message),
        );

        return $message;
    }
}
