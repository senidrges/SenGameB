<?php

namespace App\Events;

use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A profile changed (online status, avatar, username...).
 */
class ProfileUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public User $user) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("profiles.{$this->user->id}");
    }

    public function broadcastAs(): string
    {
        return 'profile.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['profile' => ProfileResource::make($this->user)->resolve()];
    }
}
