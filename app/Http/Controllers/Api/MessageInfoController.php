<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class MessageInfoController extends Controller
{
    /**
     * "Infos du message", like WhatsApp: who read it ("Lu par") and whose
     * phone received it ("Distribué à"), for its sender.
     */
    public function show(Message $message): JsonResponse
    {
        Gate::authorize('viewInfo', $message);

        $sentAt = $message->created_at;
        $read = [];
        $delivered = [];

        $recipients = $message->conversation->members()->whereKeyNot($message->sender_id)->get();

        foreach ($recipients as $recipient) {
            $readAt = $this->after($recipient->pivot->last_read_at, $sentAt);
            $deliveredAt = $this->after($recipient->pivot->last_delivered_at, $sentAt) ?? $readAt;

            // One-to-one conversations older than these columns: the flags tell
            if (! $message->conversation->isGroup()) {
                $readAt ??= $message->is_read ? $sentAt : null;
                $deliveredAt ??= $message->is_delivered || $message->is_read ? $sentAt : null;
            }

            // Only the latest progress of each member is kept, not the moment
            // they read this very message: no time is given
            if ($readAt !== null) {
                $read[] = ProfileResource::make($recipient);
            } elseif ($deliveredAt !== null) {
                $delivered[] = ProfileResource::make($recipient);
            }
        }

        return response()->json([
            'read' => $read,
            'delivered' => $delivered,
            'pending_count' => $recipients->count() - count($read) - count($delivered),
        ]);
    }

    private function after(?string $moment, Carbon $sentAt): ?Carbon
    {
        return $moment !== null && Carbon::parse($moment)->gte($sentAt) ? Carbon::parse($moment) : null;
    }
}
