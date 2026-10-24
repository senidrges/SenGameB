<?php

namespace App\Http\Controllers\Api;

use App\Events\ProfileUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteProfileRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\MeResource;
use App\Http\Resources\ProfileResource;
use App\Support\Realtime;
use Illuminate\Http\Request;

/**
 * The authenticated user's own account and profile.
 */
class ProfileController extends Controller
{
    public function show(Request $request): MeResource
    {
        return MeResource::make($request->user());
    }

    /**
     * Finish onboarding: the user gets a public profile.
     */
    public function store(CompleteProfileRequest $request): ProfileResource
    {
        $user = $request->user();

        $user->update([...$request->validated(), 'first_login_completed' => true]);

        return ProfileResource::make($user);
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $user = $request->user();

        $user->update($request->validated());

        Realtime::send(new ProfileUpdated($user));

        return ProfileResource::make($user);
    }
}
