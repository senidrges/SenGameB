<?php

namespace App\Services\Calls;

use App\Models\Call;
use App\Models\User;

/**
 * The two phones connect directly (WebRTC): free and unlimited. They
 * exchange their connection details through the API (CallSignalController),
 * and a TURN server, when configured, relays the media for networks that
 * block direct connections.
 */
class PeerToPeerProvider implements CallProvider
{
    public function name(): string
    {
        return 'p2p';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function connectionFor(Call $call, User $user): array
    {
        return [
            'provider' => $this->name(),
            'ice_servers' => $this->iceServers(),
            // The caller sends the offer, the callee answers it
            'is_offerer' => $user->id === $call->caller_id,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function iceServers(): array
    {
        $config = config('services.calls');
        $servers = [['urls' => $config['stun_urls']]];

        if ($config['turn_urls'] !== []) {
            $servers[] = [
                'urls' => $config['turn_urls'],
                'username' => $config['turn_username'],
                'credential' => $config['turn_credential'],
            ];
        }

        return $servers;
    }
}
