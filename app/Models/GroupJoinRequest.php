<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone opened the invite link of a group that needs the approval of an
 * admin ("Approuver les nouveaux membres" on WhatsApp). The row is deleted
 * once an admin approves or rejects it, or the user cancels it.
 */
#[Fillable(['conversation_id', 'user_id'])]
class GroupJoinRequest extends Model
{
    use HasUuids;

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
