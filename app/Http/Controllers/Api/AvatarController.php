<?php

namespace App\Http\Controllers\Api;

use App\Events\ProfileUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvatarController extends Controller
{
    /**
     * Replace the user's avatar. The previous one stays in the history of
     * profile photos.
     */
    public function store(Request $request): ProfileResource
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store("avatars/{$user->id}", 'public');

        DB::transaction(function () use ($user, $path): void {
            $user->update(['avatar_path' => $path]);
            $user->profilePhotos()->create(['path' => $path]);
        });

        Realtime::send(new ProfileUpdated($user));

        return ProfileResource::make($user);
    }

    /**
     * Remove the current avatar. The history of profile photos is kept.
     */
    public function destroy(Request $request): ProfileResource
    {
        $user = $request->user();

        $user->update(['avatar_path' => null]);

        Realtime::send(new ProfileUpdated($user));

        return ProfileResource::make($user);
    }
}
