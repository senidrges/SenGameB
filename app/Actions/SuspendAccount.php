<?php

namespace App\Actions;

use App\Events\ProfileUpdated;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;

class SuspendAccount
{
    /**
     * Suspend an account: every device is logged out and it can no longer
     * log in, receive notifications or be found.
     */
    public function suspend(User $user, string $reason): void
    {
        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill([
                'suspended_at' => now(),
                'suspension_reason' => $reason,
                'is_online' => false,
                'push_token' => null,
            ])->save();

            $user->tokens()->delete();
        });

        Realtime::send(new ProfileUpdated($user));
    }

    public function reinstate(User $user): void
    {
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
    }
}
