<?php

namespace App\Models;

use App\Enums\CallStatus;
use App\Enums\CallType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audio or video call between the two participants of a conversation.
 */
#[Fillable(['conversation_id', 'caller_id', 'callee_id', 'type', 'provider', 'status', 'answered_at', 'ended_at'])]
class Call extends Model
{
    use HasUuids;

    /**
     * How long the callee's phone rings before the call counts as missed.
     */
    public const RING_SECONDS = 45;

    /**
     * Past this, a call nobody hung up (phone switched off...) no longer
     * makes its participants busy.
     */
    public const MAX_HOURS = 4;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CallType::class,
            'status' => CallStatus::class,
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
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
    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function hasParticipant(User $user): bool
    {
        return $user->id === $this->caller_id || $user->id === $this->callee_id;
    }

    public function otherParticipantId(User $user): string
    {
        return $user->id === $this->caller_id ? $this->callee_id : $this->caller_id;
    }

    /**
     * Still ringing: not answered yet and the ringing time is not over.
     */
    public function isRinging(): bool
    {
        return $this->status === CallStatus::Ringing
            && $this->created_at->isAfter(now()->subSeconds(self::RING_SECONDS));
    }

    /**
     * Calls that ring or are ongoing, keeping their participants busy.
     *
     * @param  Builder<Call>  $query
     */
    #[Scope]
    protected function inProgress(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $ringing) => $ringing
                ->where('status', CallStatus::Ringing)
                ->where('created_at', '>', now()->subSeconds(self::RING_SECONDS)))
            ->orWhere(fn (Builder $ongoing) => $ongoing
                ->where('status', CallStatus::Ongoing)
                ->where('answered_at', '>', now()->subHours(self::MAX_HOURS))));
    }

    /**
     * Calls the user takes part in.
     *
     * @param  Builder<Call>  $query
     */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query->where('caller_id', $user->id)->orWhere('callee_id', $user->id));
    }
}
