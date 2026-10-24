<?php

namespace App\Models;

use App\Models\Concerns\HasReactions;
use Database\Factories\GameMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'sender_id', 'reply_to_id', 'content'])]
class GameMessage extends Model
{
    /** @use HasFactory<GameMessageFactory> */
    use HasFactory, HasReactions, HasUuids;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
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
            'reactions' => 'array',
            'is_deleted' => 'boolean',
            'edited_at' => 'datetime',
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
