<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicFile
{
    /**
     * Public URL of a file stored on the public disk.
     *
     * Absolute URLs are returned untouched: they come from records imported
     * from Supabase whose files were not copied locally.
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
