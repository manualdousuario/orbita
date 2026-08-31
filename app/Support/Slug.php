<?php

namespace App\Support;

use Cocur\Slugify\Slugify;

/** URL slug generation, ported from the legacy slugify() helper (cocur/slugify). */
class Slug
{
    /** Slugify is stateless across calls; reuse one instance. */
    private static ?Slugify $slugify = null;

    public static function make(string $text, string $separator = '-'): string
    {
        return (self::$slugify ??= new Slugify)->slugify($text, $separator);
    }
}
