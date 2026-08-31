<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rewrites whitelisted external post links through a paywall-bypass proxy.
 */
class PaywallBypass
{
    public static function enabled(): bool
    {
        return (bool) config('orbita.posts.enable_paywall_bypass', false)
            && self::rules() !== [];
    }

    public static function wrap(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        if (! self::enabled()) {
            return $url;
        }

        // Only rewrite absolute external links.
        if (! preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        $proxy = self::proxyFor($host);

        if ($proxy === null) {
            return $url;
        }

        return $proxy.'/'.$url;
    }

    /** The proxy base for a host: the most specific matching rule, else null. */
    private static function proxyFor(string $host): ?string
    {
        if ($host !== '') {
            foreach (self::rules() as $domain => $proxy) {
                if (self::matchesDomain($host, $domain)) {
                    return $proxy;
                }
            }
        }

        return null;
    }

    /**
     * Parsed per-domain rules, most specific first; malformed lines are skipped.
     *
     * @return array<string, string> domain => proxy base URL (no trailing slash)
     */
    private static function rules(): array
    {
        $rules = [];
        foreach (preg_split('/\R/', (string) config('orbita.posts.paywall_bypass_rules', '')) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$domain, $proxy] = array_map('trim', explode('=', $line, 2));
            $domain = strtolower($domain);

            if ($domain === '' || ! preg_match('#^https?://#i', $proxy)) {
                continue;
            }

            $rules[$domain] = rtrim($proxy, '/');
        }

        // Most specific domain first, so folha.uol.com beats uol.com.
        uksort($rules, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $rules;
    }

    /** "folha.uol.com" matches itself and any subdomain, but not folha.uol.com.evil.com. */
    private static function matchesDomain(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
