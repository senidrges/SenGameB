<?php

namespace App\Services\Calls;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Chooses the provider of each new call: the first available one in the
 * order of `services.calls.providers`. Peer to peer always works and closes
 * the list, so that calls keep going once a free quota is used up.
 */
class CallProviders
{
    /**
     * @var array<string, class-string<CallProvider>>
     */
    private const PROVIDERS = [
        'livekit' => LiveKitProvider::class,
        'p2p' => PeerToPeerProvider::class,
    ];

    public function __construct(private Container $container) {}

    public function forNewCall(): CallProvider
    {
        foreach (config('services.calls.providers') as $name) {
            $provider = $this->named($name);

            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        return $this->named('p2p');
    }

    /**
     * Group calls need a media server: LiveKit only, when available.
     */
    public function forGroupCall(): ?CallProvider
    {
        $livekit = $this->named('livekit');

        return in_array('livekit', config('services.calls.providers'), true) && $livekit->isAvailable() ? $livekit : null;
    }

    public function named(string $name): CallProvider
    {
        $class = self::PROVIDERS[$name] ?? throw new InvalidArgumentException("Unknown call provider [{$name}].");

        return $this->container->make($class);
    }
}
