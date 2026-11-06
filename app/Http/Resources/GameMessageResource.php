<?php

namespace App\Http\Resources;

use App\Models\GameMessage;
use App\Support\PublicFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Same shape as a conversation message, so the app shows both alike.
 *
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
            'message_type' => $this->message_type,
            'file_url' => $this->is_deleted ? null : $this->file_url,
            'file_name' => $this->is_deleted ? null : $this->file_name,
            'file_size' => $this->is_deleted ? null : $this->file_size,
            'payload' => $this->is_deleted ? null : PublicFile::withPreviewUrl($this->payload),
            'votes' => $this->is_deleted ? null : $this->votes,
            'is_read' => $this->is_read,
            'is_delivered' => $this->is_delivered || $this->is_read,
            'reply_to_id' => $this->reply_to_id,
            'reactions' => $this->reactions,
            'edited_at' => $this->edited_at,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'sender' => ProfileResource::make($this->whenLoaded('sender')),
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'content' => $this->replyTo->is_deleted ? '' : $this->replyTo->content,
                'message_type' => $this->replyTo->message_type,
                'file_url' => $this->replyTo->is_deleted ? null : $this->replyTo->file_url,
                'file_name' => $this->replyTo->is_deleted ? null : $this->replyTo->file_name,
                'payload' => $this->replyTo->is_deleted ? null : PublicFile::withPreviewUrl($this->replyTo->payload),
                'sender' => [
                    'username' => $this->replyTo->relationLoaded('sender') ? $this->replyTo->sender->username : null,
                    'avatar_url' => $this->replyTo->relationLoaded('sender') ? $this->replyTo->sender->avatar_url : null,
                ],
            ]),
        ];
    }
}
