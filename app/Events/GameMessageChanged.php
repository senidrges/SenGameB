<?php

namespace App\Events;

use App\Http\Resources\GameMessageResource;
use App\Models\GameMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message of a game chat was sent, edited, deleted or got a reaction.
 */
class GameMessageChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public const CREATED = 'game-message.created';

    public const UPDATED = 'game-message.updated';

    public function __construct(public GameMessage $message, public string $type = self::UPDATED) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("games.{$this->message->game_id}");
    }

    public function broadcastAs(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => GameMessageResource::make($this->message->loadMissing(['sender', 'replyTo.sender']))->resolve()];
    }
}
