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

    /**
     * Data of a message as sent to the app: the picture of a document's
     * first page gets its public URL.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function withPreviewUrl(?array $payload): ?array
    {
        if (! isset($payload['preview_path'])) {
            return $payload;
        }

        return [...$payload, 'preview_url' => self::url($payload['preview_path'])];
    }
}
