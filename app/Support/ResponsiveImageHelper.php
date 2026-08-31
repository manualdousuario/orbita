<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Media;

/**
 * Resolves a stored media file into a display URL from the image endpoint.
 */
class ResponsiveImageHelper
{
    private const CROPPING_TYPES = ['fill', 'crop', 'cover'];

    /** @var array<string, Media|null> */
    private static array $mediaCache = [];

    /**
     * Builds a display URL; format is always the source format, never a caller choice.
     */
    public static function buildImageUrl(string $fileHash, string $type, int $width, int $height): string
    {
        $media = self::resolveMedia($fileHash);
        if ($media === null) {
            return '';
        }

        // ImageUrl applies the animation guard: animated sources keep their source format.
        return ImageUrl::media($media, [
            'width' => $width,
            'height' => $height,
            'fit' => in_array($type, self::CROPPING_TYPES, true) ? 'crop' : 'contain',
        ]);
    }

    private static function resolveMedia(string $fileHash): ?Media
    {
        if (! array_key_exists($fileHash, self::$mediaCache)) {
            self::$mediaCache[$fileHash] = Media::query()->where('file_hash', $fileHash)->first();
        }

        return self::$mediaCache[$fileHash];
    }
}
