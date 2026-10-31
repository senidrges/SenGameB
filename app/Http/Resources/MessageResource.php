<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The content of a message deleted for everyone is never sent back.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'content' => $this->is_deleted ? null : $this->content,
            'message_type' => $this->message_type,
            'file_url' => $this->is_deleted ? null : $this->file_url,
            'file_name' => $this->is_deleted ? null : $this->file_name,
            'file_size' => $this->is_deleted ? null : $this->file_size,
            'payload' => $this->is_deleted ? null : $this->payload,
            'votes' => $this->votes,
            'is_read' => $this->is_read,
            'reply_to_id' => $this->reply_to_id,
            'reactions' => $this->reactions,
            'edited_at' => $this->edited_at,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'sender' => ProfileResource::make($this->whenLoaded('sender')),
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'content' => $this->replyTo->is_deleted ? null : $this->replyTo->content,
                'message_type' => $this->replyTo->message_type,
                'file_url' => $this->replyTo->is_deleted ? null : $this->replyTo->file_url,
                'file_name' => $this->replyTo->is_deleted ? null : $this->replyTo->file_name,
                'payload' => $this->replyTo->is_deleted ? null : $this->replyTo->payload,
                'sender' => $this->replyTo->relationLoaded('sender') ? [
                    'username' => $this->replyTo->sender->username,
                    'avatar_url' => $this->replyTo->sender->avatar_url,
                ] : null,
            ]),
        ];
    }
}
