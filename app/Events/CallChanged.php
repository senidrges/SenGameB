<?php

namespace App\Events;

use App\Http\Resources\CallResource;
use App\Models\Call;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A call started ringing, was answered, declined or ended: both phones
 * update their call screen.
 */
class CallChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Call $call) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        // Group call: every member it rang for, or who joined it
        if ($this->call->is_group) {
            return $this->call->participants()->pluck('users.id')
                ->push($this->call->caller_id)
                ->unique()
                ->map(fn (string $id) => new PrivateChannel("users.{$id}"))
                ->values()
                ->all();
        }

        return [
            new PrivateChannel("users.{$this->call->caller_id}"),
            new PrivateChannel("users.{$this->call->callee_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        if ($this->call->is_group) {
            $this->call->load('participants')->loadMissing(['caller', 'conversation']);
        }

        return ['call' => CallResource::make($this->call->loadMissing(['caller', 'callee']))->resolve()];
    }
}
