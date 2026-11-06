<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A participant is writing, or recording a voice message, in a conversation
 * or a game chat. Sent to the personal channels of the others so both the
 * chat and the lists show it. The app hides it after a few seconds without a
 * new event.
 */
class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable;

    public const TYPING = 'typing';

    public const RECORDING = 'recording';

    public const ACTIVITIES = [self::TYPING, self::RECORDING];

    /**
     * @param  list<string>  $recipientIds
     * @param  bool  $inGame  whether $conversationId is the id of a game chat
     */
    public function __construct(
        public string $conversationId,
        public string $userId,
        public array $recipientIds,
        public string $activity = self::TYPING,
        public bool $inGame = false,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (string $id) => new PrivateChannel("users.{$id}"), $this->recipientIds);
    }

    public function broadcastAs(): string
    {
        return $this->inGame ? 'game.typing' : 'conversation.typing';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            $this->inGame ? 'game_id' : 'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'activity' => $this->activity,
        ];
    }
}
