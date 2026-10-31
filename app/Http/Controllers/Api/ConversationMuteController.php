<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A muted conversation sends the user no push notification, for a number
 * of hours or until it is unmuted. Messages still arrive in the app.
 */
class ConversationMuteController extends Controller
{
    public function store(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        $hours = $validated['hours'] ?? null;

        $conversation->participants()->updateExistingPivot($request->user()->id, [
            'muted_at' => now(),
            'muted_until' => $hours === null ? null : now()->addHours($hours),
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $conversation->participants()->updateExistingPivot($request->user()->id, [
            'muted_at' => null,
            'muted_until' => null,
        ]);

        return response()->noContent();
    }
}
