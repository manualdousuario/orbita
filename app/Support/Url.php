<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Pagination\Paginator;

/**
 * URL helpers for canonicalization, pagination paths, and host checks.
 */
class Url
{
    public const COMMENT_PAGE = 'comentarios';

    public static function paginationPath(): string
    {
        return url(Paginator::resolveCurrentPath());
    }

    /**
     * Canonical URL with page parameters appended, against a given base.
     *
     * @param  string|null  $base  The URL this page should canonicalise to. Defaults to the URL
     *                             that was requested — which is wrong whenever two paths render
     *                             the same page, as `/` and `/popular` do.
     */
    public static function canonicalWithPage(array|string $pageNames = 'page', ?string $base = null): string
    {
        $query = [];

        foreach ((array) $pageNames as $pageName) {
            $page = (int) request()->query($pageName, 1);

            if ($page > 1) {
                $query[$pageName] = $page;
            }
        }

        return ($base ?? url()->current()).($query === [] ? '' : '?'.http_build_query($query));
    }

    public static function canonicalPage(?string $url, string $pageName = 'page'): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        if (($query[$pageName] ?? null) !== '1') {
            return $url;
        }

        unset($query[$pageName]);

        $base = ($parts['scheme'] ?? '') !== ''
            ? $parts['scheme'].'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '')
            : '';

        return $base.($parts['path'] ?? '').($query === [] ? '' : '?'.http_build_query($query));
    }

    public static function domain(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('#^www\.#i', '', $host);
    }

    public static function isLocal(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $url = trim($url);

        if (str_starts_with($url, '/')) {
            return preg_match('#^/[/\\\\]#', $url) !== 1;
        }

        if (str_contains($url, '\\')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $expected = parse_url((string) config('orbita.url'), PHP_URL_HOST);

        return is_string($host) && $host !== ''
            && is_string($expected) && $expected !== ''
            && $host === $expected;
    }
}
