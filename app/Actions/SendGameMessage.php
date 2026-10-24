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
     */
    public function handle(LoveGame $game, User $sender, string $content, ?string $replyToId = null): GameMessage
    {
        $message = DB::transaction(function () use ($game, $sender, $content, $replyToId): GameMessage {
            $message = $game->messages()->create([
                'sender_id' => $sender->id,
                'content' => $content,
                'reply_to_id' => $replyToId,
            ]);
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
