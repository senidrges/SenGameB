<?php

namespace App\Http\Resources;

use App\Models\ProfilePhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProfilePhoto
 */
class ProfilePhotoResource extends JsonResource
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
            'url' => $this->url,
            'is_current' => $this->whenLoaded('user', fn (): bool => $this->user->avatar_path === $this->path),
            'created_at' => $this->created_at,
        ];
    }
}
