<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsernameAvailabilityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:30'],
        ]);

        $isTaken = User::query()
            ->where('username', $validated['username'])
            ->whereKeyNot($request->user()->id)
            ->exists();

        return response()->json(['available' => ! $isTaken]);
    }
}
