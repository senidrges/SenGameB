<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message was edited, deleted for everyone or got a reaction.
 *
 * Pass the participant ids when the conversation list must refresh too
 * (e.g. the last message was deleted): it is then also sent to each
 * participant's personal channel.
 */
class MessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $participantIds
     */
    public function __construct(public Message $message, public array $participantIds = []) {}

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
        return 'message.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => MessageResource::make($this->message)->resolve()];
    }
}
