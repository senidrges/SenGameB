<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Messages of a game chat were delivered to or read by every other
 * participant: their senders' ticks change.
 */
class GameMessagesReceipts implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $readIds
     * @param  list<string>  $deliveredIds
     */
    public function __construct(public string $gameId, public array $readIds, public array $deliveredIds) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("games.{$this->gameId}");
    }

    public function broadcastAs(): string
    {
        return 'game-messages.receipts';
    }

    /**
     * @return array<string, list<string>>
     */
    public function broadcastWith(): array
    {
        return [
            'read_ids' => $this->readIds,
            'delivered_ids' => $this->deliveredIds,
        ];
    }
}
