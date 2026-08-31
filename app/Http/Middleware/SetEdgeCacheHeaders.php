<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets RFC 5861 cache headers on public HTML responses.
 */
final class SetEdgeCacheHeaders
{
    private const CACHEABLE_ROUTES = [
        'home',
        'home.popular',
        'home.all',
        'home.recent-comments',
        'home.reactions',
        'home.comments',
        'home.no-comments',
        'home.all-comments',
        'search',
        'pages.show',
        'tags.show',
        'users.profile',
        'posts.show',
        'posts.revisions',
        'comments.revisions',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->method() !== 'GET' || $response->getStatusCode() !== 200) {
            return $response;
        }

        $routeName = $request->route()?->getName();

        if (! in_array($routeName, self::CACHEABLE_ROUTES, true)) {
            return $response;
        }

        $this->varyOnCookie($response);

        if (auth()->check()) {
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        $maxAge = (int) config('orbita.cache.browser_max_age', 30);
        $sMaxAge = (int) config('orbita.cache.edge_max_age', 60);
        $swr = (int) config('orbita.cache.stale_while_revalidate', 600);
        $sie = (int) config('orbita.cache.stale_if_error', 86400);

        $response->headers->set(
            'Cache-Control',
            "public, max-age={$maxAge}, s-maxage={$sMaxAge}, stale-while-revalidate={$swr}, stale-if-error={$sie}"
        );

        return $response;
    }

    private function varyOnCookie(Response $response): void
    {
        foreach ($response->getVary() as $existing) {
            if (strcasecmp($existing, 'Cookie') === 0) {
                return;
            }
        }

        $response->setVary('Cookie', false);
    }
}
