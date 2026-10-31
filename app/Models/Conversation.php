<?php

namespace App\Models;

use Closure;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['direct_key'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUuids;

    /**
     * Unique key identifying the one-to-one conversation between two users.
     */
    public static function directKeyFor(User $first, User $second): string
    {
        $ids = [$first->id, $second->id];
        sort($ids);

        return implode(':', $ids);
    }

    /**
     * Eager load constraint giving each participant the block state with
     * the viewer: `is_blocked_by_viewer` and `has_blocked_viewer`.
     *
     * @return array<string, Closure>
     */
    public static function participantsWithBlockStateFor(User $viewer): array
    {
        return ['participants' => fn (BelongsToMany $participants) => $participants->withExists([
            'blockers as is_blocked_by_viewer' => fn (Builder $blockers) => $blockers->whereKey($viewer->id),
            'blockedUsers as has_blocked_viewer' => fn (Builder $blocked) => $blocked->whereKey($viewer->id),
        ])];
    }

    /**
     * The other participant of a one-to-one conversation.
     */
    public function otherParticipant(User $viewer): ?User
    {
        return $this->participants->first(fn (User $participant) => ! $participant->is($viewer));
    }

    public function hasParticipant(User $user): bool
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains('id', $user->id);
        }

        return $this->participants()->whereKey($user->id)->exists();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['joined_at', 'archived_at', 'locked_at']);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        // Ids are random UUIDs: the latest message is picked by date
        return $this->hasOne(Message::class)->latestOfMany(['created_at', 'id']);
    }
}
