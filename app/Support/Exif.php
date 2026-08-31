<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sanitizes EXIF metadata, stripping identifying and oversized tags.
 */
final class Exif
{
    /** Long strings are almost always encoded blobs; they have no display value either. */
    private const MAX_STRING = 512;

    /** Deep EXIF trees are pathological; the real ones are one or two levels. */
    private const MAX_DEPTH = 3;

    private const DENY_PREFIXES = ['gps'];

    private const DENY_EXACT = [
        'bodyserialnumber',
        'serialnumber',
        'internalserialnumber',
        'lensserialnumber',
        'cameraserialnumber',
        'imageuniqueid',
        'ownername',
        'cameraownername',
        'artist',
        'copyright',
        'usercomment',
        'imagedescription',
        'xpauthor',
        'xpcomment',
        'xpsubject',
        'xptitle',
        'xpkeywords',
    ];

    /**
     * Returns a json-safe copy of EXIF data with identifying tags removed.
     *
     * @param  array<mixed>  $exif
     * @return array<mixed> json-encodable and free of identifying tags, possibly empty
     */
    public static function sanitize(array $exif): array
    {
        $clean = self::clean($exif, 0);

        return is_array($clean) ? $clean : [];
    }

    /** Whether a tag (or a whole EXIF section, e.g. "GPS") must never be stored. */
    private static function isDenied(string $key): bool
    {
        $key = strtolower(trim($key));

        if (in_array($key, self::DENY_EXACT, true)) {
            return true;
        }

        foreach (self::DENY_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function clean(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (! is_int($key) && ! mb_check_encoding((string) $key, 'UTF-8')) {
                    continue;
                }

                if (! is_int($key) && self::isDenied((string) $key)) {
                    continue;
                }

                $cleaned = self::clean($item, $depth + 1);
                if ($cleaned !== null) {
                    $out[$key] = $cleaned;
                }
            }

            return $out === [] ? null : $out;
        }

        if (is_string($value)) {
            if ($value === '' || strlen($value) > self::MAX_STRING) {
                return null;
            }

            return mb_check_encoding($value, 'UTF-8') ? $value : null;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            if (is_float($value) && ! is_finite($value)) {
                return null;
            }

            return $value;
        }

        return null;
    }
}
