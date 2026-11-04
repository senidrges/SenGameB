<?php

namespace App\Actions;

use App\Enums\CallStatus;
use App\Enums\MessageType;
use App\Events\CallChanged;
use App\Events\MessageCreated;
use App\Models\Call;
use App\Notifications\MissedCall;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;

class FinishCall
{
    /**
     * End a call that still rings or is ongoing, then leave a trace of it in
     * the conversation (outgoing, incoming, missed or declined call), like
     * WhatsApp. A call already finished is left untouched.
     *
     * @return bool whether this request finished the call
     */
    public function handle(Call $call, CallStatus $status): bool
    {
        $message = DB::transaction(function () use ($call, $status) {
            // Both phones may hang up at the same time: only one of them
            // finishes the call and records it
            $finished = Call::query()
                ->whereKey($call->id)
                ->whereIn('status', [CallStatus::Ringing, CallStatus::Ongoing])
                ->update(['status' => $status, 'ended_at' => now()]);

            if ($finished === 0) {
                return null;
            }

            $call->refresh();

            $message = $call->conversation->messages()->create([
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
            // Only a missed call is new to the callee: the others are not unread
            if ($status !== CallStatus::Missed) {
                $message->forceFill(['is_delivered' => true, 'is_read' => true])->save();
            }
            $call->conversation->touch();

            return $message;
        });

        if ($message === null) {
            return false;
        }

        Realtime::send(new CallChanged($call));

        $message->load('sender');
        Realtime::send(new MessageCreated($message, $call->conversation->participants()->pluck('users.id')->all()));

        if ($status === CallStatus::Missed) {
            $call->callee->notify(new MissedCall($call));
        }

        return true;
    }
}
