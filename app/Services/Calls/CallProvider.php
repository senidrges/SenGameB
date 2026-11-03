<?php

namespace App\Services\Calls;

use App\Models\Call;
use App\Models\User;

/**
 * A service carrying the sound and video of calls (LiveKit, peer to peer,
 * later Agora or a self-hosted server). The app picks the matching engine
 * from the `provider` of the connection, so adding one needs no change to
 * the call flow.
 */
interface CallProvider
{
    /**
     * Stored on the call and sent to the app.
     */
    public function name(): string;

    /**
     * Whether new calls can use it now (configured, free quota left...).
     */
    public function isAvailable(): bool;

    /**
     * What the user's app needs to join the call.
     *
     * @return array<string, mixed>
     */
    public function connectionFor(Call $call, User $user): array;
}
