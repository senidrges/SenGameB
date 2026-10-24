<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sent to the conversation and to each participant's personal channel, so
 * the conversation list refreshes too.
 */
class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $participantIds
     */
    public function __construct(public Message $message, public array $participantIds) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversations.{$this->message->conversation_id}"),
            ...array_map(fn (string $id) => new PrivateChannel("users.{$id}"), $this->participantIds),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => MessageResource::make($this->message->loadMissing(['sender', 'replyTo.sender']))->resolve()];
    }
}
