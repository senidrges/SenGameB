<?php

namespace App\Policies;

use App\Models\ContactRequest;
use App\Models\User;

class ContactRequestPolicy
{
    /**
     * Only the invited user accepts or declines.
     */
    public function respond(User $user, ContactRequest $contactRequest): bool
    {
        return $contactRequest->recipient_id === $user->id;
    }

    /**
     * Only the sender cancels their invitation.
     */
    public function cancel(User $user, ContactRequest $contactRequest): bool
    {
        return $contactRequest->sender_id === $user->id;
    }
}
