<?php

namespace App\Http\Controllers\Api;

use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\LoveGame;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GameTypingController extends Controller
{
    /**
     * Tell the other participants the user is writing or recording a voice
     * message in the game chat.
     */
    public function store(Request $request, LoveGame $game): Response
    {
        Gate::authorize('view', $game);

        $validated = $request->validate([
            'activity' => ['nullable', Rule::in(UserTyping::ACTIVITIES)],
        ]);

        $user = $request->user();
        $recipientIds = array_values(array_diff($game->participantIds(), [$user->id]));

        if ($recipientIds !== []) {
            Realtime::send(new UserTyping(
                $game->id,
                $user->id,
                $recipientIds,
                $validated['activity'] ?? UserTyping::TYPING,
                inGame: true,
            ));
        }

        return response()->noContent();
    }
}
