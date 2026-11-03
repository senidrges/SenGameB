<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Connection details of a peer to peer call (WebRTC offer or answer),
 * passed on to the other phone.
 */
class CallSignal implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $signal
     */
    public function __construct(public string $callId, public string $recipientId, public array $signal) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("users.{$this->recipientId}");
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'signal' => $this->signal,
        ];
    }
}
