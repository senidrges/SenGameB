<?php

namespace App\Http\Resources;

use App\Models\Message;
use App\Support\PublicFile;
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
     * The content of a message deleted for everyone is never sent back,
     * even though it is kept in the database.
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
            'payload' => $this->is_deleted ? null : PublicFile::withPreviewUrl($this->payload),
            'votes' => $this->is_deleted ? null : $this->votes,
            'is_read' => $this->is_read,
            'is_delivered' => $this->is_delivered || $this->is_read,
            'reply_to_id' => $this->reply_to_id,
            'reactions' => $this->is_deleted ? null : $this->reactions,
            'edited_at' => $this->edited_at,
            'is_deleted' => $this->is_deleted,
            // Group: an admin deleted it, WhatsApp shows "Supprimé par un admin"
            'deleted_by_admin' => $this->is_deleted && $this->deleted_by_id !== null
                ? ['id' => $this->deleted_by_id, 'username' => $this->deletedBy?->username]
                : null,
            'is_forwarded' => $this->forwarded_from_id !== null,
            // Members named with @ (group)
            'mentions' => $this->relationLoaded('mentions') ? $this->mentions->modelKeys() : [],
            // Disappearing messages: when it goes
            'expires_at' => $this->expires_at,
            // "admins": a notice only the admins see
            'audience' => $this->audience,
            'created_at' => $this->created_at,
            'sender' => ProfileResource::make($this->whenLoaded('sender')),
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'content' => $this->replyTo->is_deleted ? null : $this->replyTo->content,
                'message_type' => $this->replyTo->message_type,
                'file_url' => $this->replyTo->is_deleted ? null : $this->replyTo->file_url,
                'file_name' => $this->replyTo->is_deleted ? null : $this->replyTo->file_name,
                'payload' => $this->replyTo->is_deleted ? null : PublicFile::withPreviewUrl($this->replyTo->payload),
                'sender' => $this->replyTo->relationLoaded('sender') ? [
                    'username' => $this->replyTo->sender->username,
                    'avatar_url' => $this->replyTo->sender->avatar_url,
                ] : null,
            ]),
        ];
    }
}
