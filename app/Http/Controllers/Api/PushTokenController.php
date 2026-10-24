<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushTokenController extends Controller
{
    /**
     * Register the Expo push token of the user's device.
     *
     * A device token belongs to a single account: it is removed from any
     * other account that used the same phone before.
     */
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        User::query()
            ->where('push_token', $validated['token'])
            ->whereKeyNot($user->id)
            ->update(['push_token' => null]);

        $user->update(['push_token' => $validated['token']]);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $request->user()->update(['push_token' => null]);

        return response()->noContent();
    }
}
