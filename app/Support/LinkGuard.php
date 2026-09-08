<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

class LinkGuard
{
    public static function enabled(): bool
    {
        return (bool) config('orbita.moderation.link_guard_enabled', false);
    }

    public static function domains(string $key): array
    {
        $domains = [];

        foreach (preg_split('/\R/', (string) config('orbita.moderation.'.$key, '')) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $domain = strtolower($line);

            foreach (['*.', 'www.'] as $prefix) {
                if (str_starts_with($domain, $prefix)) {
                    $domain = substr($domain, strlen($prefix));
                }
            }

            if ($domain !== '') {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    public static function matches(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }

    public static function isBlocked(string $host): bool
    {
        foreach (self::domains('link_domain_blocklist') as $domain) {
            if (self::matches($host, $domain)) {
                return true;
            }
        }

        return false;
    }

    public static function isAllowed(string $host): bool
    {
        $own = MarkdownUrls::host((string) config('app.url'));

        if ($own !== null && self::matches($host, $own)) {
            return true;
        }

        foreach (self::domains('link_domain_allowlist') as $domain) {
            if (self::matches($host, $domain)) {
                return true;
            }
        }

        return false;
    }

    public static function isTrusted(?User $user, ?int $excludeCommentId = null): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        $minHours = (int) config('orbita.moderation.link_trust_account_age_hours', 72);

        if ($minHours > 0) {
            $createdAt = $user->created_at;

            if ($createdAt === null || $createdAt->copy()->addHours($minHours)->isFuture()) {
                return false;
            }
        }

        $minComments = (int) config('orbita.moderation.link_trust_min_comments', 3);

        if ($minComments > 0) {
            $published = Comment::query()
                ->where('user_id', $user->id)
                ->where('status', 'visible')
                ->whereNull('version_of')
                ->when($excludeCommentId !== null, fn ($q) => $q->whereKeyNot($excludeCommentId))
                ->count();

            if ($published < $minComments) {
                return false;
            }
        }

        return true;
    }

    public static function report(Post|Comment|User $target, ?string $markdown, ?string $url = null): void
    {
        if (! self::enabled()) {
            return;
        }

        $bodyHosts = MarkdownUrls::hosts((string) $markdown);
        $urlHost = MarkdownUrls::host($url);

        $blocked = array_values(array_filter(
            $urlHost !== null ? [...$bodyHosts, $urlHost] : $bodyHosts,
            self::isBlocked(...),
        ));

        if ($blocked !== []) {
            self::flag($target, 'blocked_domain', $blocked);

            return;
        }

        $author = $target instanceof User ? $target : $target->user;

        if (self::isTrusted($author, $target instanceof Comment ? (int) $target->id : null)) {
            return;
        }

        $external = array_values(array_filter($bodyHosts, fn (string $host) => ! self::isAllowed($host)));

        if ($external !== []) {
            self::flag($target, 'untrusted_author', $external);
        }
    }

    /**
     * @param  list<string>  $hosts
     */
    private static function flag(Post|Comment|User $target, string $reason, array $hosts): void
    {
        $type = match (true) {
            $target instanceof Post => 'post',
            $target instanceof Comment => 'comment',
            default => 'user',
        };

        $hidden = $target instanceof User ? false : ContentVisibility::hideForReview($target);

        $message = $reason === 'blocked_domain'
            ? 'Link de domínio bloqueado: '.implode(', ', $hosts)
            : 'Link externo publicado por conta sem histórico: '.implode(', ', $hosts);

        ModerationLogger::system(
            'link_flagged',
            $type,
            (int) $target->id,
            $message,
            ['reason' => $reason, 'hosts' => $hosts, 'hidden' => $hidden],
        );
    }
}
