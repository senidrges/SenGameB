<?php

namespace App\Models;

use App\Enums\ContactRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation to talk sent to someone whose number the sender does not
 * have, like a friend request on Facebook. Once accepted, the two users can
 * talk and play together. A declined invitation can be sent again.
 */
#[Fillable(['sender_id', 'recipient_id', 'status', 'message', 'responded_at'])]
class ContactRequest extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === ContactRequestStatus::Pending;
    }

    /**
     * Requests between the two users, in either direction.
     */
    #[Scope]
    protected function between(Builder $query, User $first, User $second): void
    {
        $query->where(fn (Builder $pair) => $pair
            ->where(fn (Builder $sent) => $sent->where('sender_id', $first->id)->where('recipient_id', $second->id))
            ->orWhere(fn (Builder $received) => $received->where('sender_id', $second->id)->where('recipient_id', $first->id)));
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', ContactRequestStatus::Pending);
    }

    #[Scope]
    protected function accepted(Builder $query): void
    {
        $query->where('status', ContactRequestStatus::Accepted);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
