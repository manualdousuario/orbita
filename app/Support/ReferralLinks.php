<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

/**
 * Strips forbidden referral parameters from URLs and audits removals.
 */
class ReferralLinks
{
    private const URL_PATTERN = '#(?:https?://|www\.)[^\s<>()\[\]"\'`]+#i';

    /** Sentence punctuation that trails a URL in prose and is not part of it. */
    private const TRAILING_PUNCTUATION = '.,;:!?';

    public static function enabled(): bool
    {
        return (bool) config('orbita.moderation.strip_referral_params', false)
            && self::params() !== [];
    }

    /**
     * The configured parameter names, lowercased; '*' is a prefix wildcard.
     *
     * @return list<string>
     */
    public static function params(): array
    {
        $params = [];

        foreach (preg_split('/\R/', (string) config('orbita.moderation.forbidden_url_params', '')) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $params[] = strtolower($line);
        }

        return array_values(array_unique($params));
    }

    /** True when a query-parameter name is forbidden ("utm_*" matches by prefix). */
    public static function matches(string $name): bool
    {
        $name = strtolower($name);

        foreach (self::params() as $param) {
            if (str_ends_with($param, '*')) {
                $prefix = rtrim($param, '*');

                if ($prefix !== '' && str_starts_with($name, $prefix)) {
                    return true;
                }

                continue;
            }

            if ($name === $param) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes forbidden parameters from a single URL.
     */
    public static function clean(?string $url): ?string
    {
        return self::cleanLink($url)['url'];
    }

    /**
     * Same as clean(), also reporting which parameters were removed.
     *
     * @return array{url: string|null, stripped: list<string>}
     */
    public static function cleanLink(?string $url): array
    {
        [$clean, $stripped] = self::cleanUrl($url);

        return ['url' => $clean, 'stripped' => $stripped];
    }

    /**
     * Audits the removal and optionally hides the content for review.
     *
     * @param  list<string>  $stripped
     */
    public static function report(Post|Comment|User $target, array $stripped): void
    {
        if ($stripped === []) {
            return;
        }

        $type = match (true) {
            $target instanceof Post => 'post',
            $target instanceof Comment => 'comment',
            default => 'user',
        };

        $hidden = $target instanceof User ? false : self::hideForReview($target);

        ModerationLogger::system(
            'referral_stripped',
            $type,
            (int) $target->id,
            'Link com código de referência removido automaticamente: '.implode(', ', $stripped),
            ['params' => $stripped, 'hidden' => $hidden],
        );
    }

    /**
     * Hides visible content for review until a moderator acts.
     */
    private static function hideForReview(Post|Comment $target): bool
    {
        if (! config('orbita.moderation.hide_referral_for_review', false)) {
            return false;
        }

        $eligible = $target instanceof Post ? ['published', 'closed'] : ['visible'];

        if (! in_array((string) $target->status, $eligible, true)) {
            return false;
        }

        $target->update(['status' => 'hidden']);

        return true;
    }

    /**
     * Rewrites every URL inside a Markdown body.
     *
     * @return array{content: string, stripped: list<string>} the sanitized text and the
     *                                                        names of the parameters removed
     */
    public static function cleanText(string $markdown): array
    {
        if ($markdown === '' || ! self::enabled()) {
            return ['content' => $markdown, 'stripped' => []];
        }

        $tokens = preg_split(
            '/(```[\s\S]*?```|~~~[\s\S]*?~~~|`+[^`]*?`+)/',
            $markdown,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($tokens === false) {
            return ['content' => $markdown, 'stripped' => []];
        }

        $stripped = [];

        foreach ($tokens as $i => $token) {
            if ($i % 2 === 1) {
                continue;
            }

            $tokens[$i] = (string) preg_replace_callback(
                self::URL_PATTERN,
                function (array $m) use (&$stripped): string {
                    $url = rtrim($m[0], self::TRAILING_PUNCTUATION);
                    $tail = substr($m[0], strlen($url));

                    [$clean, $names] = self::cleanUrl($url);

                    foreach ($names as $name) {
                        $stripped[] = $name;
                    }

                    return $clean.$tail;
                },
                $token
            );
        }

        return [
            'content' => implode('', $tokens),
            'stripped' => array_values(array_unique($stripped)),
        ];
    }

    /**
     * The shared single-URL rewrite.
     *
     * @return array{0: string|null, 1: list<string>} cleaned URL and the parameters removed
     */
    private static function cleanUrl(?string $url): array
    {
        if ($url === null || trim($url) === '' || ! self::enabled()) {
            return [$url, []];
        }

        $parts = parse_url($url);

        if ($parts === false || ($parts['query'] ?? '') === '') {
            return [$url, []];
        }

        $kept = [];
        $stripped = [];

        foreach (explode('&', (string) $parts['query']) as $pair) {
            if ($pair === '') {
                continue;
            }

            $name = urldecode(explode('=', $pair, 2)[0]);

            if (self::matches($name)) {
                $stripped[] = strtolower($name);

                continue;
            }

            $kept[] = $pair;
        }

        if ($stripped === []) {
            return [$url, []];
        }

        $rebuilt = '';

        if (isset($parts['scheme'])) {
            $rebuilt .= $parts['scheme'].'://';
        }

        if (isset($parts['user'])) {
            $rebuilt .= $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@';
        }

        $rebuilt .= $parts['host'] ?? '';

        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        $rebuilt .= $parts['path'] ?? '';

        if ($kept !== []) {
            $rebuilt .= '?'.implode('&', $kept);
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return [$rebuilt, array_values(array_unique($stripped))];
    }
}
