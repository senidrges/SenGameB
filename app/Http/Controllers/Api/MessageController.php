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

        Realtime::send(new MessageUpdated($message->load('mentions:id')));

        return MessageResource::make($message);
    }

    /**
     * Delete a message for everyone. It stays in the thread as "deleted" and
     * its content is kept in the database (soft delete).
     */
    public function destroy(Request $request, Message $message): MessageResource
    {
        Gate::authorize('delete', $message);

        // A group admin may delete the message of someone else
        $message->markDeletedForEveryone($request->user());
        $message->load('mentions:id');

        // The conversation lists show the last message: they refresh too
        Realtime::send(new MessageUpdated($message, $message->conversation->members()->pluck('users.id')->all()));

        return MessageResource::make($message);
    }
}
