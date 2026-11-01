<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Models\Concerns\HasReactions;
use App\Support\PublicFile;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'conversation_id',
    'sender_id',
    'reply_to_id',
    'content',
    'message_type',
    'file_path',
    'file_name',
    'file_size',
    'payload',
    'forwarded_from_id',
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
            'payload' => 'array',
            'votes' => 'array',
            'is_read' => 'boolean',
            'is_deleted' => 'boolean',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    /**
     * Delete the message for everyone: it stays in the thread as "deleted".
     * Nothing is erased: the content and file stay in the database, only
     * MessageResource stops sending them.
     */
    public function markDeletedForEveryone(): void
    {
        if ($this->is_deleted) {
            return;
        }

        $this->forceFill(['is_deleted' => true, 'deleted_at' => now()])->save();
    }

    /**
     * Messages the user can see: not deleted "for me" and not older than
     * the moment they deleted the conversation.
     *
     * @param  Builder<Message>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query
            ->whereDoesntHave('hiddenBy', fn (Builder $users) => $users->whereKey($user->id))
            ->whereNotExists(fn (QueryBuilder $participants) => $participants
                ->from('conversation_participants')
                ->whereColumn('conversation_participants.conversation_id', 'messages.conversation_id')
                ->where('conversation_participants.user_id', $user->id)
                ->whereColumn('conversation_participants.deleted_at', '>=', 'messages.created_at'));
    }

    /**
     * Ids of the answers a poll or an event offers.
     *
     * @return list<string>
     */
    public function voteOptions(): array
    {
        return match ($this->message_type) {
            MessageType::Poll => array_column($this->payload['options'] ?? [], 'id'),
            MessageType::Event => ['going', 'maybe', 'not_going'],
            default => [],
        };
    }

    /**
     * Replace the user's answers to a poll or an event (none to withdraw).
     *
     * The row is locked so two users voting at the same time never
     * overwrite each other.
     *
     * @param  list<string>  $optionIds
     */
    public function setVotes(string $userId, array $optionIds): void
    {
        DB::transaction(function () use ($userId, $optionIds): void {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $votes = [];
            foreach ($this->voteOptions() as $option) {
                $users = array_values(array_diff($locked->votes[$option] ?? [], [$userId]));
                if (in_array($option, $optionIds, true)) {
                    $users[] = $userId;
                }
                if ($users !== []) {
                    $votes[$option] = $users;
                }
            }

            $this->forceFill(['votes' => $votes === [] ? null : $votes])->save();
        });
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

    /**
     * Users who deleted this message "for me".
     *
     * @return BelongsToMany<User, $this>
     */
    public function hiddenBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hidden_messages')->withPivot('hidden_at');
    }
}
