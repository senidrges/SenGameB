<?php

namespace App\Models;

use App\Enums\CallParticipantStatus;
use App\Enums\CallStatus;
use App\Enums\CallType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * An audio or video call: between the two participants of a conversation,
 * or a group call (`is_group`) where every member rings and anyone joins
 * or leaves while it goes on, like WhatsApp. A group call is "ongoing" from
 * the start (the caller waits in it) until its last participant leaves.
 */
#[Fillable(['conversation_id', 'caller_id', 'callee_id', 'is_group', 'type', 'provider', 'status', 'answered_at', 'ended_at'])]
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
            'is_group' => 'boolean',
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

    /**
     * Members of a group call (the caller included).
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'call_participants')
            ->withPivot(['status', 'invited_at', 'joined_at', 'left_at']);
    }

    public function hasParticipant(User $user): bool
    {
        if ($this->is_group) {
            return $user->id === $this->caller_id || $this->participants()->whereKey($user->id)->exists();
        }

        return $user->id === $this->caller_id || $user->id === $this->callee_id;
    }

    public function otherParticipantId(User $user): string
    {
        return $user->id === $this->caller_id ? $this->callee_id : $this->caller_id;
    }

    /**
     * Where the user stands in a group call, a ring left unanswered counting
     * as missed.
     */
    public function participantStatus(object $pivot): CallParticipantStatus
    {
        $status = CallParticipantStatus::from($pivot->status);

        if ($status === CallParticipantStatus::Ringing
            && ($this->status->isFinished() || Carbon::parse($pivot->invited_at)->isBefore(now()->subSeconds(self::RING_SECONDS)))) {
            return CallParticipantStatus::Missed;
        }

        return $status;
    }

    /**
     * Group call: whether the user's phone still rings for it.
     */
    public function isRingingFor(User $user): bool
    {
        $pivot = $this->participants()->whereKey($user->id)->first()?->pivot;

        return $pivot !== null && $this->participantStatus($pivot) === CallParticipantStatus::Ringing;
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
     * Calls the user takes part in: as caller or callee, or in a group call
     * they are in (or that rings for them).
     *
     * @param  Builder<Call>  $query
     */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('caller_id', $user->id)
            ->orWhere('callee_id', $user->id)
            ->orWhereExists(fn (QueryBuilder $participants) => $participants
                ->from('call_participants')
                ->whereColumn('call_participants.call_id', 'calls.id')
                ->where('call_participants.user_id', $user->id)
                ->where(fn (QueryBuilder $active) => $active
                    ->where('call_participants.status', CallParticipantStatus::Joined->value)
                    ->orWhere(fn (QueryBuilder $ringing) => $ringing
                        ->where('call_participants.status', CallParticipantStatus::Ringing->value)
                        ->where('call_participants.invited_at', '>', now()->subSeconds(self::RING_SECONDS))))));
    }
}
