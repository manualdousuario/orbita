<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Static page (guidelines, terms, footer links).
 */
class Page extends Model
{
    use HasFactory;

    public const FOOTER_CACHE_KEY = 'orbita:footer-pages';

    protected static function booted(): void
    {
        $flush = static fn () => Cache::forget(self::FOOTER_CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'slug',
        'title',
        'content',
        'show_in_footer',
        'is_guidelines',
        'is_terms',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'show_in_footer' => 'boolean',
            'is_guidelines' => 'boolean',
            'is_terms' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
