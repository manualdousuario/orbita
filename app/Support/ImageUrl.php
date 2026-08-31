<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\URL;

/**
 * Builds URLs for the on-the-fly image endpoint.
 */
class ImageUrl
{
    /** Formats the endpoint may emit -- the accepted upload formats only; no WebP/AVIF. */
    public const FORMATS = ['jpg', 'png', 'gif'];

    /**
     * URL for a media record with resize options.
     *
     * @param  array{width?:int,height?:int,fit?:string,quality?:int}  $opts
     */
    public static function media(Media $media, array $opts = []): string
    {
        $opts['format'] = $media->file_extension ?: self::sourceFormat((string) $media->path);

        return self::path((string) $media->path, $opts);
    }

    /**
     * URL for a stored path with resize options.
     *
     * @param  array{width?:int,height?:int,fit?:string,format?:string,quality?:int}  $opts
     */
    public static function path(string $path, array $opts = []): string
    {
        $params = array_filter([
            'w' => $opts['width'] ?? null,
            'h' => $opts['height'] ?? null,
            'fit' => $opts['fit'] ?? null,
            'fm' => $opts['format'] ?? self::sourceFormat($path),
            'q' => $opts['quality'] ?? config('orbita.images.quality', 82),
        ], static fn ($v) => $v !== null && $v !== '');

        return URL::route('image', ['path' => ltrim($path, '/')] + $params);
    }

    /** Direct, unprocessed URL - used as the <img> fallback and for animated originals. */
    public static function original(string $path): string
    {
        return URL::route('image', ['path' => ltrim($path, '/')]);
    }

    public static function isAnimated(Media $media): bool
    {
        return (bool) (is_array($media->metadata) ? ($media->metadata['animated'] ?? false) : false);
    }

    /** The stored file's own format, capped to what the endpoint emits (jpg for anything else). */
    private static function sourceFormat(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;

        return in_array($ext, self::FORMATS, true) ? $ext : 'jpg';
    }
}
