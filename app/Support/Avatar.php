<?php

namespace App\Support;

/**
 * Builds DiceBear avatar URLs.
 */
class Avatar
{
    private const API = 'https://api.dicebear.com/10.x';

    /**
     * @param  string  $seed  stable identifier (usually the username)
     * @param  string|null  $style  DiceBear style slug; defaults to the configured style
     */
    public static function url(string $seed, ?string $style = null): string
    {
        $style = $style ?: (string) config('orbita.avatar_style', 'thumbs');

        return self::API.'/'.rawurlencode($style).'/svg?seed='.rawurlencode($seed);
    }

    /**
     * Returns the same avatar as PNG for local storage at account creation.
     */
    public static function pngUrl(string $seed, ?string $style = null, ?int $size = null): string
    {
        $style = $style ?: (string) config('orbita.avatar_style', 'thumbs');
        $size = $size ?: (int) config('orbita.images.avatar_size', 300);

        return self::API.'/'.rawurlencode($style).'/png'
            .'?seed='.rawurlencode($seed)
            .'&size='.$size;
    }
}
