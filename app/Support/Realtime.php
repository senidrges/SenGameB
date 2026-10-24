<?php

namespace App\Support;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class Realtime
{
    /**
     * Broadcast an event without failing the request when the WebSocket
     * server is unreachable: the data is already saved and clients catch up
     * on their next fetch. The failure is still reported.
     */
    public static function send(ShouldBroadcast $event): void
    {
        rescue(function () use ($event): void {
            broadcast($event);
        });
    }
}
