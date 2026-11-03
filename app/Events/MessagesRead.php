<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A participant read every message the others sent in a conversation. Also
 * sent to the senders' personal channels for the ticks of the conversation
 * list.
 */
class MessagesRead implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $senderIds
     */
    public function __construct(public string $conversationId, public string $readerId, public array $senderIds = []) {}

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
        return 'messages.read';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'reader_id' => $this->readerId,
        ];
    }
}
