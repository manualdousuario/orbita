<?php

declare(strict_types=1);

namespace App\Support;

class MarkdownUrls
{
    private const URL_PATTERN = '#(?:https?://|www\.)[^\s<>()\[\]"\'`]+#i';

    private const TRAILING_PUNCTUATION = '.,;:!?';

    public static function map(string $markdown, callable $callback): string
    {
        if ($markdown === '') {
            return $markdown;
        }

        $tokens = preg_split(
            '/(```[\s\S]*?```|~~~[\s\S]*?~~~|`+[^`]*?`+)/',
            $markdown,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($tokens === false) {
            return $markdown;
        }

        foreach ($tokens as $i => $token) {
            if ($i % 2 === 1) {
                continue;
            }

            $tokens[$i] = (string) preg_replace_callback(
                self::URL_PATTERN,
                function (array $m) use ($callback): string {
                    $url = rtrim($m[0], self::TRAILING_PUNCTUATION);
                    $tail = substr($m[0], strlen($url));

                    return $callback($url).$tail;
                },
                $token
            );
        }

        return implode('', $tokens);
    }

    public static function extract(string $markdown): array
    {
        $urls = [];

        self::map($markdown, function (string $url) use (&$urls): string {
            $urls[] = $url;

            return $url;
        });

        return array_values(array_unique($urls));
    }

    public static function hosts(string $markdown): array
    {
        $hosts = [];

        foreach (self::extract($markdown) as $url) {
            $host = self::host($url);

            if ($host !== null) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    public static function host(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
