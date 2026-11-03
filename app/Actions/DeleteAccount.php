<?php

namespace App\Actions;

use App\Models\Message;
use App\Models\User;
use App\Support\AvatarThumbnail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAccount
{
    /**
     * Delete an account and everything it owns: profile, messages, games,
     * blocks and reports go with the user row (cascade), files are removed
     * from the disk unless a forwarded copy still uses them.
     */
    public function delete(User $user): void
    {
        $attachmentPaths = Message::query()
            ->where('sender_id', $user->id)
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->unique();

        // Every profile photo the user has set, the current one included
        $profilePhotoPaths = $user->profilePhotos()->pluck('path')
            ->push($user->avatar_path)
            ->filter(fn (?string $path): bool => $path !== null && ! str_starts_with($path, 'http'))
            ->unique();

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        $stillUsedPaths = Message::query()->whereIn('file_path', $attachmentPaths)->pluck('file_path');

        Storage::disk('public')->delete([
            ...$attachmentPaths->diff($stillUsedPaths)->values(),
            ...$profilePhotoPaths->values(),
        ]);
        AvatarThumbnail::delete($profilePhotoPaths);
    }
}
