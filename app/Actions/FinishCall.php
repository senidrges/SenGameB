<?php

namespace App\Actions;

use App\Enums\CallStatus;
use App\Enums\MessageType;
use App\Events\CallChanged;
use App\Events\MessageCreated;
use App\Models\Call;
use App\Models\User;
use App\Notifications\MissedCall;
use App\Support\Realtime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinishCall
{
    /**
     * End a call that still rings or is ongoing, then leave a trace of it in
     * the conversation (outgoing, incoming, missed or declined call), like
     * WhatsApp. A call already finished is left untouched.
     *
     * @param  Carbon|null  $endedAt  when the call really ended, if not now
     * @return bool whether this request finished the call
     */
    public function handle(Call $call, CallStatus $status, ?Carbon $endedAt = null): bool
    {
        $message = DB::transaction(function () use ($call, $status, $endedAt) {
            // Both phones may hang up at the same time: only one of them
            // finishes the call and records it
            $finished = Call::query()
                ->whereKey($call->id)
                ->whereIn('status', [CallStatus::Ringing, CallStatus::Ongoing])
                ->update(['status' => $status, 'ended_at' => $endedAt ?? now()]);

            if ($finished === 0) {
                return null;
            }

            $call->refresh();

            $message = $call->conversation->messages()->make([
                'sender_id' => $call->caller_id,
                'message_type' => MessageType::Call,
                'payload' => [
                    'call_id' => $call->id,
                    'call_type' => $call->type->value,
                    'status' => $call->status->value,
                    'duration' => $call->answered_at === null
                        ? 0
                        : (int) $call->answered_at->diffInSeconds($call->ended_at),
                ],
            ]);
            // The trace sits where the call ended in the conversation
            $message->created_at = $call->ended_at;
            // Only a missed call is new to the callee: the others are not unread
            if ($status !== CallStatus::Missed) {
                $message->forceFill(['is_delivered' => true, 'is_read' => true]);
            }
            $message->save();
            $call->conversation->touch();

            return $message;
        });

        if ($message === null) {
            return false;
        }

        Realtime::send(new CallChanged($call));

        $message->load('sender');
        Realtime::send(new MessageCreated($message, $call->conversation->participants()->pluck('users.id')->all()));

        // A call found expired long after is not worth a push any more
        if ($status === CallStatus::Missed && $endedAt === null) {
            $call->callee->notify(new MissedCall($call));
        }

        return true;
    }

    /**
     * Calls of the user still marked as ringing although their ringing time
     * is over: nobody reported the end (app closed, network lost). They are
     * finished as missed so the conversation keeps a trace of them.
     */
    public function finishExpiredFor(User $user): void
    {
        Call::query()
            ->involving($user)
            ->where('status', CallStatus::Ringing)
            ->where('created_at', '<=', now()->subSeconds(Call::RING_SECONDS))
            ->get()
            ->each(fn (Call $call) => $this->handle(
                $call,
                CallStatus::Missed,
                $call->created_at->copy()->addSeconds(Call::RING_SECONDS),
            ));
    }
}
