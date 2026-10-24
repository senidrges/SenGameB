<?php

namespace App\Http\Resources;

use App\Models\GameMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameMessage
 */
class GameMessageResource extends JsonResource
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
            'game_id' => $this->game_id,
            'sender_id' => $this->sender_id,
            'content' => $this->is_deleted ? '' : $this->content,
            'reply_to_id' => $this->reply_to_id,
            'reactions' => $this->reactions,
            'edited_at' => $this->edited_at,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'sender' => ProfileResource::make($this->whenLoaded('sender')),
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'content' => $this->replyTo->is_deleted ? '' : $this->replyTo->content,
                'sender' => [
                    'username' => $this->replyTo->relationLoaded('sender') ? $this->replyTo->sender->username : null,
                ],
            ]),
        ];
    }
}
