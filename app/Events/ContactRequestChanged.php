<?php

namespace App\Events;

use App\Models\ContactRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An invitation was sent, accepted, declined or cancelled. Sent to the
 * personal channels of both users so their invitation lists stay in sync.
 */
class ContactRequestChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public ContactRequest $contactRequest) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("users.{$this->contactRequest->sender_id}"),
            new PrivateChannel("users.{$this->contactRequest->recipient_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'contact-request.changed';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->contactRequest->id,
            'sender_id' => $this->contactRequest->sender_id,
            'recipient_id' => $this->contactRequest->recipient_id,
            'status' => $this->contactRequest->status->value,
        ];
    }
}
