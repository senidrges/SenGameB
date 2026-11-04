<?php

namespace App\Http\Controllers\Api;

use App\Actions\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageForwardController extends Controller
{
    /**
     * Send a copy of a message to another conversation of the user. The copy
     * starts fresh (no reply, reactions or votes) and remembers its origin.
     */
    public function store(Request $request, Message $message, SendMessage $sendMessage): JsonResponse
    {
        Gate::authorize('view', $message);
        abort_if($message->is_deleted, 422, 'Ce message a été supprimé.');
        abort_unless($message->message_type->canBeSent(), 422, 'Ce message ne peut pas être transféré.');

        $validated = $request->validate([
            'conversation_id' => ['required', 'uuid', 'exists:conversations,id'],
        ]);

        $target = Conversation::findOrFail($validated['conversation_id']);
        Gate::authorize('sendMessage', $target);

        $forwarded = $sendMessage->handle($target, $request->user(), [
            'message_type' => $message->message_type->value,
            'content' => $message->content,
            'file_path' => $message->file_path,
            'file_name' => $message->file_name,
            'file_size' => $message->file_size,
            'payload' => $message->payload,
            'forwarded_from_id' => $message->id,
        ]);

        return MessageResource::make($forwarded)->response()->setStatusCode(201);
    }
}
