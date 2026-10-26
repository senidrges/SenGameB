<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Archiving hides a conversation from the user's main list, for them only.
 * It stays archived when new messages arrive.
 */
class ConversationArchiveController extends Controller
{
    public function store(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $conversation->participants()->updateExistingPivot($request->user()->id, ['archived_at' => now()]);

        return response()->noContent();
    }

    public function destroy(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $conversation->participants()->updateExistingPivot($request->user()->id, ['archived_at' => null]);

        return response()->noContent();
    }
}
