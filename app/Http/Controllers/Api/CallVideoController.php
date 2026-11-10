<?php

namespace App\Http\Controllers\Api;

use App\Enums\CallStatus;
use App\Enums\CallType;
use App\Events\CallChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\CallResource;
use App\Models\Call;
use App\Support\Realtime;
use Illuminate\Http\Request;

/**
 * Either side turns its camera on during an audio call: it becomes a video
 * call, and the other phone offers to turn its camera on too (like WhatsApp).
 */
class CallVideoController extends Controller
{
    public function store(Request $request, Call $call): CallResource
    {
        abort_unless($call->hasParticipant($request->user()), 404);
        abort_unless($call->status === CallStatus::Ongoing, 409, 'Cet appel est terminé.');

        if ($call->type !== CallType::Video) {
            $call->update(['type' => CallType::Video]);
            Realtime::send(new CallChanged($call));
        }

        return CallResource::make($call->load(['caller', 'callee', 'participants', 'conversation']));
    }
}
