<?php

namespace App\Http\Controllers\Api;

use App\Events\ProfileUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfilePhotoResource;
use App\Models\ProfilePhoto;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * History of the profile photos of a user, newest first.
 */
class ProfilePhotoController extends Controller
{
    /**
     * Visible to whoever can see the profile: a blocked user (either way)
     * gets a not found, like for the profile itself.
     */
    public function index(Request $request, User $user): AnonymousResourceCollection
    {
        abort_unless($user->hasProfile() && ! $user->isSuspended() && ! $request->user()->hasBlockWith($user), 404);

        $photos = $user->profilePhotos()
            ->latest()
            ->latest('id')
            ->get()
            ->each->setRelation('user', $user);

        return ProfilePhotoResource::collection($photos);
    }

    /**
     * Remove one of the user's own photos from the history (and the profile
     * when it is the current one).
     */
    public function destroy(Request $request, ProfilePhoto $profilePhoto): Response
    {
        $user = $request->user();
        abort_unless($profilePhoto->user_id === $user->id, 404);

        $isCurrent = $user->avatar_path === $profilePhoto->path;

        DB::transaction(function () use ($user, $profilePhoto, $isCurrent): void {
            $profilePhoto->delete();

            if ($isCurrent) {
                $user->update(['avatar_path' => null]);
            }
        });

        if (! str_starts_with($profilePhoto->path, 'http')) {
            Storage::disk('public')->delete($profilePhoto->path);
        }

        if ($isCurrent) {
            Realtime::send(new ProfileUpdated($user));
        }

        return response()->noContent();
    }
}
