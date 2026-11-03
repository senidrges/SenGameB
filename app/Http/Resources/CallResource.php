<?php

namespace App\Http\Resources;

use App\Enums\CallStatus;
use App\Models\Call;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A call, shaped like the app's `Call` type. Load the caller and the callee.
 *
 * @mixin Call
 */
class CallResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Nobody reported the end of the ringing: it was missed
        $status = $this->status === CallStatus::Ringing && ! $this->isRinging()
            ? CallStatus::Missed
            : $this->status;

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'type' => $this->type,
            'provider' => $this->provider,
            'status' => $status,
            'caller' => $this->participant($this->caller),
            'callee' => $this->participant($this->callee),
            'created_at' => $this->created_at,
            'answered_at' => $this->answered_at,
            'ended_at' => $this->ended_at,
        ];
    }

    /**
     * Sent to both sides: only what the call screen shows.
     *
     * @return array<string, string|null>
     */
    private function participant(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'avatar_thumb_url' => $user->avatar_thumb_url,
        ];
    }
}
