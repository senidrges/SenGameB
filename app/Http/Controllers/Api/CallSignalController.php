<?php

namespace App\Http\Controllers\Api;

use App\Enums\CallStatus;
use App\Events\CallSignal;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Peer to peer calls: each phone sends its connection details (WebRTC offer
 * or answer, with every network candidate in it) to the other through here.
 */
class CallSignalController extends Controller
{
    public function store(Request $request, Call $call): Response
    {
        $user = $request->user();
        abort_unless($call->hasParticipant($user), 404);
        abort_unless($call->status === CallStatus::Ongoing, 409, 'Cet appel est terminé.');

        $validated = $request->validate([
            'type' => ['required', 'in:offer,answer'],
            'sdp' => ['required', 'string', 'max:9000'],
        ]);

        Realtime::send(new CallSignal($call->id, $call->otherParticipantId($user), $validated));

        return response()->noContent();
    }
}
