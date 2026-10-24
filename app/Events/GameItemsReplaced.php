<?php

namespace App\Events;

use App\Http\Resources\GameItemResource;
use App\Models\LoveGame;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The organizer rewrote the list of items of a game.
 */
class GameItemsReplaced implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public LoveGame $game) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("games.{$this->game->id}");
    }

    public function broadcastAs(): string
    {
        return 'game.items-replaced';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['items' => GameItemResource::collection($this->game->items)->resolve()];
    }
}
