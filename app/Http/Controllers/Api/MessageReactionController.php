<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageReactionController extends Controller
{
    /**
     * Toggle the user's reaction on a message.
     */
    public function store(Request $request, Message $message): MessageResource
    {
        Gate::authorize('sendMessage', $message->conversation);

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $message->toggleReaction($validated['emoji'], $request->user()->id);

        Realtime::send(new MessageUpdated($message));

        return MessageResource::make($message);
    }
}
