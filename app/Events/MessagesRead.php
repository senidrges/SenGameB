<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A participant read every message the others sent in a conversation.
 */
class MessagesRead implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $conversationId, public string $readerId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("conversations.{$this->conversationId}");
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
