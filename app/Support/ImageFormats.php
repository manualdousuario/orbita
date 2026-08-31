<?php

namespace App\Support;

/**
 * The single source of truth for accepted upload formats and their extensions.
 */
class ImageFormats
{
    /**
     * MIME => canonical extension for formats accepted on upload.
     */
    public const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    /**
     * Extensions that can carry more than one frame (broader than the upload set).
     */
    public const ANIMATABLE = ['gif', 'webp', 'avif'];

    /** @return list<string> */
    public static function allowedMimes(): array
    {
        return array_keys(self::MIME_EXTENSIONS);
    }

    /**
     * Extensions for a Laravel mimes rule, derived from the allowlist.
     *
     * @return list<string>
     */
    public static function ruleExtensions(): array
    {
        $extensions = array_values(self::MIME_EXTENSIONS);

        if (in_array('jpg', $extensions, true) && ! in_array('jpeg', $extensions, true)) {
            $extensions[] = 'jpeg';
        }

        return $extensions;
    }

    /** The comma-separated extension list for a Laravel `mimes:` rule, e.g. "jpg,png,gif,jpeg". */
    public static function mimesRule(): string
    {
        return implode(',', self::ruleExtensions());
    }

    /** The value for an HTML file input `accept` attribute, e.g. "image/jpeg,image/png,image/gif". */
    public static function acceptAttribute(): string
    {
        return implode(',', self::allowedMimes());
    }

    public static function isAnimatable(string $extension): bool
    {
        return in_array(strtolower($extension), self::ANIMATABLE, true);
    }

    public static function isAllowed(?string $mime): bool
    {
        return $mime !== null && array_key_exists($mime, self::MIME_EXTENSIONS);
    }

    public static function extensionFor(?string $mime, string $fallback = 'jpg'): string
    {
        return self::MIME_EXTENSIONS[$mime] ?? $fallback;
    }

    /** Human-readable list for validation messages. */
    public static function label(): string
    {
        return 'JPEG, PNG, GIF';
    }
}
