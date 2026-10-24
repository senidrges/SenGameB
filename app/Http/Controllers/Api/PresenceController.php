<?php

namespace App\Http\Controllers\Api;

use App\Events\ProfileUpdated;
use App\Http\Controllers\Controller;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PresenceController extends Controller
{
    /**
     * Mark the user online or offline.
     */
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $user->update(['is_online' => $validated['is_online'], 'last_seen_at' => now()]);

        if ($user->hasProfile()) {
            Realtime::send(new ProfileUpdated($user));
        }

        return response()->noContent();
    }
}
