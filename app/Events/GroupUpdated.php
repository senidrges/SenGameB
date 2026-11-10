<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A group changed (name, photo, members, admins, permissions, join
 * requests): the phones load it again. Sent to the personal channels so
 * that someone just removed learns it too.
 */
class GroupUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $userIds
     */
    public function __construct(public string $conversationId, public array $userIds) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (string $id) => new PrivateChannel("users.{$id}"), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'group.updated';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId];
    }
}
