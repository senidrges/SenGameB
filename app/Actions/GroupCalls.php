<?php

namespace App\Actions;

use App\Enums\CallParticipantStatus;
use App\Enums\CallStatus;
use App\Enums\CallType;
use App\Events\CallChanged;
use App\Events\GroupUpdated;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\IncomingCall;
use App\Services\Calls\CallProviders;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;

/**
 * Group calls, like WhatsApp: the caller enters the call and every member
 * of the group rings; anyone joins (or rejoins) while it goes on, and the
 * call ends when its last participant leaves. Only LiveKit carries them
 * (peer to peer links two phones only).
 */
class GroupCalls
{
    public function __construct(private CallProviders $providers, private FinishCall $finishCall) {}

    public function start(Conversation $group, User $caller, CallType $type): Call
    {
        $provider = $this->providers->forGroupCall();
        abort_if($provider === null, 422, 'Les appels de groupe ne sont pas disponibles pour le moment.');

        // A group call already going on: join it instead, like WhatsApp
        $ongoing = $this->ongoingIn($group);
        if ($ongoing !== null) {
            $this->join($ongoing, $caller);

            return $ongoing;
        }

        $busyIds = Call::query()->inProgress()->get()
            ->flatMap(fn (Call $call) => $call->is_group
                ? $call->participants()->wherePivot('status', CallParticipantStatus::Joined->value)->pluck('users.id')
                : [$call->caller_id, $call->callee_id])
            ->filter()
            ->unique()
            ->all();
        abort_if(in_array($caller->id, $busyIds, true), 409, 'Vous êtes déjà en communication.');

        // Muted groups do not ring, nor members already in a call
        $invited = $group->members()->whereKeyNot($caller->id)->get()
            ->reject(fn (User $member) => Conversation::pivotIsMuted($member->pivot) || in_array($member->id, $busyIds, true));

        $call = DB::transaction(function () use ($group, $caller, $type, $provider, $invited): Call {
            $now = now();
            $call = Call::create([
                'conversation_id' => $group->id,
                'caller_id' => $caller->id,
                'callee_id' => null,
                'is_group' => true,
                'type' => $type,
                'provider' => $provider->name(),
                'status' => CallStatus::Ongoing,
                'answered_at' => $now,
            ]);

            $call->participants()->attach([
                $caller->id => ['status' => CallParticipantStatus::Joined->value, 'invited_at' => $now, 'joined_at' => $now],
                ...$invited->mapWithKeys(fn (User $member) => [$member->id => [
                    'status' => CallParticipantStatus::Ringing->value,
                    'invited_at' => $now,
                    'joined_at' => null,
                ]])->all(),
            ]);

            return $call;
        });

        $call->setRelation('conversation', $group)->setRelation('caller', $caller);
        Realtime::send(new CallChanged($call));
        $this->groupChanged($group);

        foreach ($invited as $member) {
            $member->notify(new IncomingCall($call));
        }

        return $call;
    }

    /**
     * Answer the ringing, or join an ongoing call of one's group.
     */
    public function join(Call $call, User $user): void
    {
        abort_unless($call->status === CallStatus::Ongoing, 409, 'Cet appel est terminé.');
        abort_unless($call->conversation->hasMember($user), 403);

        $attributes = ['status' => CallParticipantStatus::Joined->value, 'joined_at' => now(), 'left_at' => null];

        $call->participants()->whereKey($user->id)->exists()
            ? $call->participants()->updateExistingPivot($user->id, $attributes)
            : $call->participants()->attach($user->id, [...$attributes, 'invited_at' => now()]);

        Realtime::send(new CallChanged($call));
        $this->groupChanged($call->conversation);
    }

    public function decline(Call $call, User $user): void
    {
        $pivot = $call->participants()->whereKey($user->id)->first()?->pivot;

        if ($pivot === null || $call->participantStatus($pivot) !== CallParticipantStatus::Ringing) {
            return;
        }

        $call->participants()->updateExistingPivot($user->id, ['status' => CallParticipantStatus::Declined->value]);
        Realtime::send(new CallChanged($call));
    }

    /**
     * Hang up. The call ends with its last participant.
     */
    public function leave(Call $call, User $user): void
    {
        if ($call->status->isFinished()) {
            return;
        }

        $pivot = $call->participants()->whereKey($user->id)->first()?->pivot;

        if ($pivot !== null && $pivot->status === CallParticipantStatus::Joined->value) {
            $call->participants()->updateExistingPivot($user->id, [
                'status' => CallParticipantStatus::Left->value,
                'left_at' => now(),
            ]);
        } elseif ($pivot !== null && $call->participantStatus($pivot) === CallParticipantStatus::Ringing) {
            $this->decline($call, $user);

            return;
        }

        $this->finishIfEmpty($call);
    }

    /**
     * Nobody left in the call: it ends, and those who never answered missed it.
     */
    public function finishIfEmpty(Call $call): void
    {
        if ($call->participants()->wherePivot('status', CallParticipantStatus::Joined->value)->exists()) {
            Realtime::send(new CallChanged($call));
            $this->groupChanged($call->conversation);

            return;
        }

        $this->finishCall->handle($call, CallStatus::Ended);
        $this->groupChanged($call->conversation);
    }

    /**
     * The group's "Appel en cours · Rejoindre" banner follows the call.
     */
    private function groupChanged(Conversation $group): void
    {
        Realtime::send(new GroupUpdated($group->id, $group->members()->pluck('users.id')->all()));
    }

    public function ongoingIn(Conversation $group): ?Call
    {
        return Call::query()
            ->where('conversation_id', $group->id)
            ->where('is_group', true)
            ->where('status', CallStatus::Ongoing)
            ->where('answered_at', '>', now()->subHours(Call::MAX_HOURS))
            ->whereHas('participants', fn ($participants) => $participants->where('call_participants.status', CallParticipantStatus::Joined->value))
            ->latest()
            ->first();
    }
}
