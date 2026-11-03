<?php

namespace App\Http\Controllers\Api;

use App\Enums\CallStatus;
use App\Enums\CallType;
use App\Events\CallChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\CallResource;
use App\Models\Call;
use App\Models\Conversation;
use App\Notifications\IncomingCall;
use App\Notifications\MissedCall;
use App\Services\Calls\CallProviders;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Audio and video calls: the caller starts one, the callee answers or
 * declines it, either side hangs up.
 */
class CallController extends Controller
{
    public function store(Request $request, Conversation $conversation, CallProviders $providers): CallResource
    {
        Gate::authorize('sendMessage', $conversation);

        $validated = $request->validate([
            'type' => ['required', Rule::enum(CallType::class)],
        ]);

        $caller = $request->user();
        $callee = $conversation->participants()->whereKeyNot($caller->id)->firstOrFail();

        $isBusy = Call::query()
            ->inProgress()
            ->where(fn ($query) => $query->involving($caller)->orWhere(fn ($query) => $query->involving($callee)))
            ->exists();
        abort_if($isBusy, 409, 'Votre correspondant est déjà en communication.');

        $call = Call::create([
            'conversation_id' => $conversation->id,
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
            'type' => $validated['type'],
            'provider' => $providers->forNewCall()->name(),
            'status' => CallStatus::Ringing,
        ])->setRelation('caller', $caller)->setRelation('callee', $callee)->setRelation('conversation', $conversation);

        Realtime::send(new CallChanged($call));
        $callee->notify(new IncomingCall($call));

        return CallResource::make($call);
    }

    /**
     * Opened from the push notification: is it still ringing?
     */
    public function show(Request $request, Call $call): CallResource
    {
        abort_unless($call->hasParticipant($request->user()), 404);

        return CallResource::make($call->load(['caller', 'callee']));
    }

    /**
     * The callee picks up: both sides then ask for their connection.
     */
    public function accept(Request $request, Call $call, CallProviders $providers): JsonResponse
    {
        $user = $request->user();
        abort_unless($call->callee_id === $user->id, 404);
        abort_unless($call->isRinging(), 409, 'Cet appel est terminé.');

        $call->update(['status' => CallStatus::Ongoing, 'answered_at' => now()]);
        Realtime::send(new CallChanged($call));

        return response()->json([
            ...CallResource::make($call->load(['caller', 'callee']))->resolve($request),
            'connection' => $providers->named($call->provider)->connectionFor($call, $user),
        ]);
    }

    public function decline(Request $request, Call $call): Response
    {
        abort_unless($call->callee_id === $request->user()->id, 404);

        if ($call->status === CallStatus::Ringing) {
            $call->update(['status' => CallStatus::Declined, 'ended_at' => now()]);
            Realtime::send(new CallChanged($call));
        }

        return response()->noContent();
    }

    /**
     * Hang up. Before the answer, the callee missed the call.
     */
    public function end(Request $request, Call $call): Response
    {
        abort_unless($call->hasParticipant($request->user()), 404);

        if ($call->status->isFinished()) {
            return response()->noContent();
        }

        $wasRinging = $call->status === CallStatus::Ringing;
        $call->update([
            'status' => $wasRinging ? CallStatus::Missed : CallStatus::Ended,
            'ended_at' => now(),
        ]);
        Realtime::send(new CallChanged($call));

        if ($wasRinging) {
            $call->callee->notify(new MissedCall($call));
        }

        return response()->noContent();
    }

    /**
     * What the user's app needs to join an answered call.
     */
    public function connection(Request $request, Call $call, CallProviders $providers): JsonResponse
    {
        $user = $request->user();
        abort_unless($call->hasParticipant($user), 404);
        abort_unless($call->status === CallStatus::Ongoing, 409, 'Cet appel est terminé.');

        return response()->json($providers->named($call->provider)->connectionFor($call, $user));
    }
}
