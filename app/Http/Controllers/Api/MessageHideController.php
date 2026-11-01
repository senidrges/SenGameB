<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Deleting a message "for me" hides it from the user only, on all their
 * devices. The message itself is never erased.
 */
class MessageHideController extends Controller
{
    public function store(Request $request, Message $message): Response
    {
        Gate::authorize('view', $message);

        $message->hiddenBy()->syncWithoutDetaching([$request->user()->id]);

        return response()->noContent();
    }
}
