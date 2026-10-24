<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlockedUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Accounts the user blocked. A block works both ways: neither user can
 * write to or find the other. The blocked user is not told.
 */
class BlockController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $blockedUsers = $request->user()
            ->blockedUsers()
            ->orderByPivot('created_at', 'desc')
            ->get();

        return BlockedUserResource::collection($blockedUsers);
    }

    public function store(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => ['required', 'uuid', Rule::notIn([$user->id]), Rule::exists('users', 'id')],
        ], [
            'user_id.not_in' => 'Vous ne pouvez pas vous bloquer vous-même.',
        ]);

        $user->blockedUsers()->syncWithoutDetaching([$validated['user_id'] => ['created_at' => now()]]);

        return response()->noContent();
    }

    public function destroy(Request $request, User $user): Response
    {
        $request->user()->blockedUsers()->detach($user->id);

        return response()->noContent();
    }
}
