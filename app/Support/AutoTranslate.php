<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Wraps external links through a configured URL translator when the post is flagged non-Portuguese.
 */
class AutoTranslate
{
    /** Literal `[en]`/`[es]` tag submitters put in the title to flag a non-Portuguese link. */
    private const LANGUAGE_MARKER_PATTERN = '/\[(en|es)\]/i';

    public static function enabled(): bool
    {
        return (bool) config('orbita.posts.enable_auto_translate', false);
    }

    public static function hasLanguageMarker(?string $title): bool
    {
        return $title !== null && preg_match(self::LANGUAGE_MARKER_PATTERN, $title) === 1;
    }

    public static function wrap(?string $url, ?string $title = null): ?string
    {
        if ($url === null || $url === '' || ! self::enabled()) {
            return $url;
        }

        if (! self::hasLanguageMarker($title)) {
            return $url;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $template = trim((string) config('orbita.posts.translate_base_url', ''));

        if ($template === '' || ! str_contains($template, '{url}')) {
            return $url;
        }

        return str_replace('{url}', rawurlencode($url), $template);
    }
}
