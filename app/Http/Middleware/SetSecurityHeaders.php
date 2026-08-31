<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies security headers and CSP to responses.
 */
final class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->headers->set(
            'Permissions-Policy',
            (string) config('orbita.security.permissions_policy', 'camera=(), microphone=(), geolocation=()')
        );

        if ($this->isHtml($response)) {
            $this->applyContentSecurityPolicy($response);
        }

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function applyContentSecurityPolicy(Response $response): void
    {
        $directives = (array) config('orbita.security.csp.directives', []);

        if ($directives === []) {
            return;
        }

        $parts = [];

        foreach ($directives as $directive => $sources) {
            $sources = array_values(array_filter(array_map('trim', (array) $sources), fn (string $s): bool => $s !== ''));

            $parts[] = $sources === [] ? $directive : $directive.' '.implode(' ', $sources);
        }

        if (filled($reportUri = config('orbita.security.csp.report_uri'))) {
            $parts[] = 'report-uri '.$reportUri;
        }

        $header = config('orbita.security.csp.enforce')
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->headers->set($header, implode('; ', $parts));
    }
}
