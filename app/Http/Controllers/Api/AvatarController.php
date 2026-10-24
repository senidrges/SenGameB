<?php

namespace App\Http\Controllers\Api;

use App\Events\ProfileUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    /**
     * Replace the user's avatar.
     */
    public function store(Request $request): ProfileResource
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $user = $request->user();
        $previousPath = $user->avatar_path;

        $user->update(['avatar_path' => $request->file('avatar')->store("avatars/{$user->id}", 'public')]);

        if ($previousPath !== null && ! str_starts_with($previousPath, 'http')) {
            Storage::disk('public')->delete($previousPath);
        }

        Realtime::send(new ProfileUpdated($user));

        return ProfileResource::make($user);
    }
}
