<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated account and its profile (null until onboarding is done).
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->id,
                'email' => $this->email,
            ],
            'profile' => $this->hasProfile() ? ProfileResource::make($this->resource) : null,
        ];
    }
}
