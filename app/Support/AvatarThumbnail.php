<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Small square copy of a profile photo for the lists of the app (conversations,
 * contacts…), where the full photo would cost mobile data for a 45px circle.
 */
class AvatarThumbnail
{
    private const SIZE = 160;

    private const QUALITY = 75;

    /**
     * Where the thumbnail of a photo is stored: next to it, in a thumbs folder.
     */
    public static function path(string $photoPath): string
    {
        return dirname($photoPath).'/thumbs/'.pathinfo($photoPath, PATHINFO_FILENAME).'.jpg';
    }

    /**
     * Public URL of the thumbnail, created on first use for photos uploaded
     * before thumbnails existed. Falls back to the photo itself when it cannot
     * be made (photo missing or not decodable).
     */
    public static function url(?string $photoPath): ?string
    {
        if ($photoPath === null || $photoPath === '' || str_starts_with($photoPath, 'http')) {
            return PublicFile::url($photoPath);
        }

        $path = self::path($photoPath);

        if (! Storage::disk('public')->exists($path) && ! self::create($photoPath)) {
            return PublicFile::url($photoPath);
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Crop the middle square of the photo and shrink it.
     */
    public static function create(string $photoPath): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $disk = Storage::disk('public');
        $contents = $disk->exists($photoPath) ? $disk->get($photoPath) : null;
        $source = $contents === null ? false : @imagecreatefromstring($contents);

        if ($source === false) {
            return false;
        }

        $side = min(imagesx($source), imagesy($source));
        $thumbnail = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            intdiv(imagesx($source) - $side, 2),
            intdiv(imagesy($source) - $side, 2),
            self::SIZE,
            self::SIZE,
            $side,
            $side,
        );

        ob_start();
        imagejpeg($thumbnail, null, self::QUALITY);

        return $disk->put(self::path($photoPath), ob_get_clean());
    }

    /**
     * Remove the thumbnails of these photos.
     *
     * @param  iterable<string>  $photoPaths
     */
    public static function delete(iterable $photoPaths): void
    {
        $paths = [];
        foreach ($photoPaths as $photoPath) {
            if (! str_starts_with($photoPath, 'http')) {
                $paths[] = self::path($photoPath);
            }
        }

        Storage::disk('public')->delete($paths);
    }
}
