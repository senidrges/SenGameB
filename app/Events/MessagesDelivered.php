<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The phone of a participant received the messages the others sent in a
 * conversation (two grey ticks). Sent to the conversation and to the senders'
 * personal channels.
 */
class MessagesDelivered implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $senderIds
     */
    public function __construct(public string $conversationId, public string $recipientId, public array $senderIds) {}

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
        return 'messages.delivered';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'recipient_id' => $this->recipientId,
        ];
    }
}
