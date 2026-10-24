<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Profiles of the other users.
 */
class UserController extends Controller
{
    /**
     * Search users by username.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:30'],
        ]);

        $users = User::query()
            ->whereNotNull('username')
            ->whereKeyNot($request->user()->id)
            ->withoutBlocksInvolving($request->user())
            ->whereNull('suspended_at')
            ->whereLike('username', '%'.addcslashes($validated['search'], '\\%_').'%')
            ->orderBy('username')
            ->limit(20)
            ->get();

        return ProfileResource::collection($users);
    }

    /**
     * A blocked user (either way) is reported as not found, like on other social networks.
     */
    public function show(Request $request, User $user): ProfileResource
    {
        abort_unless($user->hasProfile() && ! $user->isSuspended() && ! $request->user()->hasBlockWith($user), 404);

        return ProfileResource::make($user);
    }
}
