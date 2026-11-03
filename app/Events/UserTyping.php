<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A participant is writing in a conversation. Sent to the personal channels
 * of the others so both the chat and the conversation list show it. The app
 * hides it after a few seconds without a new event.
 */
class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $recipientIds
     */
    public function __construct(public string $conversationId, public string $userId, public array $recipientIds) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (string $id) => new PrivateChannel("users.{$id}"), $this->recipientIds);
    }

    public function broadcastAs(): string
    {
        return 'conversation.typing';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
        ];
    }
}
