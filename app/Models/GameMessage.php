<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Models\Concerns\HasReactions;
use App\Models\Concerns\HasVotes;
use App\Support\PublicFile;
use Database\Factories\GameMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message of a game chat. Like a conversation message, it can be a voice
 * message, a photo, a file, a location, a contact, a poll or an event.
 */
#[Fillable([
    'game_id',
    'sender_id',
    'reply_to_id',
    'content',
    'message_type',
    'file_path',
    'file_name',
    'file_size',
    'payload',
])]
class GameMessage extends Model
{
    /** @use HasFactory<GameMessageFactory> */
    use HasFactory, HasReactions, HasUuids, HasVotes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'message_type' => 'text',
        'is_delivered' => false,
        'is_read' => false,
        'is_deleted' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'message_type' => MessageType::class,
            'reactions' => 'array',
            'payload' => 'array',
            'votes' => 'array',
            'is_delivered' => 'boolean',
            'is_read' => 'boolean',
            'is_deleted' => 'boolean',
            'edited_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    /**
     * Delete the message for everyone: it stays in the chat as "deleted".
     */
    public function markDeletedForEveryone(): void
    {
        $this->forceFill(['is_deleted' => true, 'content' => '', 'reactions' => null])->save();
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => PublicFile::url($this->file_path));
    }

    /**
     * @return BelongsTo<LoveGame, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(LoveGame::class, 'game_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<GameMessage, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(GameMessage::class, 'reply_to_id');
    }
}
