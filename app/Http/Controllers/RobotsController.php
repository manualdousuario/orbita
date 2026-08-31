<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves robots.txt with an appended sitemap URL.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = preg_split('/\R/', trim((string) config('orbita.robots.content'))) ?: [];

        $lines = array_values(array_filter(
            $lines,
            static fn (string $line): bool => ! str_starts_with(ltrim(mb_strtolower($line)), 'sitemap:'),
        ));

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap.index');

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
