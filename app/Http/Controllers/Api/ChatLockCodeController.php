<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Secret code of the locked conversations: once set, the app hides the
 * locked folder and only opens it when the code is typed in the search bar.
 */
class ChatLockCodeController extends Controller
{
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:32'],
        ]);

        $request->user()->update(['chat_lock_code' => $validated['code']]);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $request->user()->update(['chat_lock_code' => null]);

        return response()->noContent();
    }

    /**
     * Check a code typed in the search bar (throttled against guessing).
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $hashedCode = $request->user()->chat_lock_code;

        return response()->json([
            'valid' => $hashedCode !== null && Hash::check($validated['code'], $hashedCode),
        ]);
    }
}
