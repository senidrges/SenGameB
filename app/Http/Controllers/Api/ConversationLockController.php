<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A locked conversation moves to the user's locked folder, opened with the
 * device authentication, and its push notifications hide the sender and
 * the content. A locked conversation is never archived.
 */
class ConversationLockController extends Controller
{
    public function store(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $conversation->participants()->updateExistingPivot($request->user()->id, [
            'locked_at' => now(),
            'archived_at' => null,
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $conversation->participants()->updateExistingPivot($request->user()->id, ['locked_at' => null]);

        return response()->noContent();
    }
}
