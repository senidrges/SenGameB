<?php

namespace App\Services\Calls;

use App\Enums\CallStatus;
use App\Models\Call;
use App\Models\User;

/**
 * LiveKit Cloud relays the media through its servers: reliable on every
 * network, but its free plan counts participant minutes. New calls stop
 * using it before the monthly allowance runs out.
 */
class LiveKitProvider implements CallProvider
{
    public function name(): string
    {
        return 'livekit';
    }

    public function isAvailable(): bool
    {
        $config = config('services.livekit');

        if (blank($config['url']) || blank($config['api_key']) || blank($config['api_secret'])) {
            return false;
        }

        return $this->participantMinutesThisMonth() < $config['monthly_minutes'] * $config['usage_limit'];
    }

    public function connectionFor(Call $call, User $user): array
    {
        $config = config('services.livekit');

        return [
            'provider' => $this->name(),
            'url' => $config['url'],
            'token' => HmacJwt::encode([
                'iss' => $config['api_key'],
                'sub' => $user->id,
                'name' => $user->username,
                'nbf' => now()->timestamp,
                'exp' => now()->addHours(Call::MAX_HOURS)->timestamp,
                'video' => [
                    'room' => $call->id,
                    'roomJoin' => true,
                    'canPublish' => true,
                    'canSubscribe' => true,
                    'canPublishData' => false,
                ],
            ], $config['api_secret']),
        ];
    }

    /**
     * Minutes billed by LiveKit this month: each answered call counts once
     * per participant, a started minute counting in full.
     */
    public function participantMinutesThisMonth(): int
    {
        return (int) Call::query()
            ->where('provider', $this->name())
            ->where('answered_at', '>=', now()->startOfMonth())
            ->get(['answered_at', 'ended_at', 'status'])
            ->sum(fn (Call $call): int => 2 * $this->minutes($call));
    }

    private function minutes(Call $call): int
    {
        // Not hung up yet (or the end was never reported): counted up to now,
        // within the longest possible call
        $endedAt = $call->ended_at ?? ($call->status === CallStatus::Ongoing
            ? now()->min($call->answered_at->copy()->addHours(Call::MAX_HOURS))
            : $call->answered_at);

        return max(1, (int) ceil($call->answered_at->diffInSeconds($endedAt) / 60));
    }
}
