<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public profile of a user, shaped like the app's `Profile` type.
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone_number' => $this->phone_number,
            'avatar_url' => $this->avatar_url,
            'status' => $this->status,
            'is_online' => $this->is_online,
            'last_seen' => $this->last_seen_at ?? $this->updated_at,
            'biometrics_enabled' => $this->biometrics_enabled,
            'first_login_completed' => $this->first_login_completed,
            'is_admin' => $this->is_admin,
            'created_at' => $this->created_at,
            'role' => $this->whenPivotLoaded('game_participants', fn () => $this->pivot->role),
        ];
    }
}
