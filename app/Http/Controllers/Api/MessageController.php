<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageType;
use App\Events\MessageUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    /**
     * Edit the text of a message.
     */
    public function update(Request $request, Message $message): MessageResource
    {
        Gate::authorize('update', $message);

        abort_unless($message->message_type === MessageType::Text, 422, 'Seuls les messages texte peuvent être modifiés.');

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $message->forceFill(['content' => $validated['content'], 'edited_at' => now()])->save();

        Realtime::send(new MessageUpdated($message));

        return MessageResource::make($message);
    }

    /**
     * Delete a message for everyone. It stays in the thread as "deleted".
     */
    public function destroy(Message $message): MessageResource
    {
        Gate::authorize('delete', $message);

        $message->markDeletedForEveryone();

        Realtime::send(new MessageUpdated($message));

        return MessageResource::make($message);
    }
}
