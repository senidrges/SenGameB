<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Models\Concerns\HasReactions;
use App\Support\PublicFile;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id',
    'sender_id',
    'reply_to_id',
    'content',
    'message_type',
    'file_path',
    'file_name',
    'file_size',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, HasReactions, HasUuids;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'message_type' => 'text',
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
            'is_read' => 'boolean',
            'is_deleted' => 'boolean',
            'edited_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    /**
     * Delete the message for everyone: it stays in the thread as "deleted"
     * but its content and file are gone.
     */
    public function markDeletedForEveryone(): void
    {
        $this->forceFill([
            'is_deleted' => true,
            'content' => null,
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
            'reactions' => null,
        ])->save();
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => PublicFile::url($this->file_path));
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }
}
