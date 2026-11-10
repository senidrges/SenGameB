<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Messages of a group now received or read by every other member (see
 * UpdateGroupReceipts). Also sent to the senders' personal channels for the
 * ticks of the conversation list.
 */
class GroupMessagesReceipts implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $readIds
     * @param  list<string>  $deliveredIds
     * @param  list<string>  $senderIds
     */
    public function __construct(
        public string $conversationId,
        public array $readIds,
        public array $deliveredIds,
        public array $senderIds = [],
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversations.{$this->conversationId}"),
            ...array_map(fn (string $id) => new PrivateChannel("users.{$id}"), $this->senderIds),
        ];
    }

    public function broadcastAs(): string
    {
        return 'messages.receipts';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'read_ids' => $this->readIds,
            'delivered_ids' => $this->deliveredIds,
        ];
    }
}
