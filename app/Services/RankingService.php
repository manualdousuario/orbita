<?php

namespace App\Services;

use App\Models\Post;
use App\Support\FeedGeneration;
use App\Support\HomeFeedTabs;
use App\Support\Url;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes post scores and ranking queries for the home feed tabs.
 */
class RankingService
{
    private int $decayHours;

    public function __construct()
    {
        $this->decayHours = max(1, (int) config('orbita.posts.score_decay_hours', 3));
    }

    /**
     * Computes a post's score: reactions plus comments minus time decay.
     */
    public function calculatePostScore(int $postId): int
    {
        $post = Post::query()->find($postId);

        if (! $post) {
            return 0;
        }

        $reactionScore = $this->reactionScore($postId);
        $commentScore = $this->commentScore($postId, (int) $post->user_id);

        $publishedTime = strtotime($post->published_at ?? $post->created_at);

        $wholeHours = (int) ((time() - $publishedTime) / 3600);
        $temporalPenalty = (int) floor($wholeHours / $this->decayHours);

        return max(0, $reactionScore + $commentScore - $temporalPenalty);
    }

    private function reactionScore(int $postId): int
    {
        return (int) DB::table('user_reactions as ur')
            ->join('reaction_types as rt', 'ur.reaction_type_id', '=', 'rt.id')
            ->where('ur.reactable_type', 'post')
            ->where('ur.reactable_id', $postId)
            ->sum('rt.score');
    }

    private function commentScore(int $postId, int $authorId): int
    {
        return (int) DB::table('comments')
            ->where('post_id', $postId)
            ->where('user_id', '!=', $authorId)
            ->whereNull('deleted_at')
            ->where('status', 'visible')
            ->count();
    }

    private const SCORE_SQL = "GREATEST(0,
        COALESCE((
            SELECT SUM(rt.score)
            FROM user_reactions ur
            JOIN reaction_types rt ON ur.reaction_type_id = rt.id
            WHERE ur.reactable_type = 'post' AND ur.reactable_id = p.id
        ), 0)
        + (
            SELECT COUNT(*)
            FROM comments c
            WHERE c.post_id = p.id
              AND c.user_id != p.user_id
              AND c.deleted_at IS NULL
              AND c.status = 'visible'
        )
        - FLOOR(TIMESTAMPDIFF(HOUR, COALESCE(p.published_at, p.created_at), NOW()) / ?)
    )";

    private const RECALC_CHUNK = 500;

    public static int $chunkSize = self::RECALC_CHUNK;

    /**
     * Recomputes scores for all published posts; returns rows changed.
     */
    public function recalculateAllScores(): int
    {
        $changed = 0;
        $lastId = 0;

        while (true) {
            $ids = DB::table('posts')
                ->where('id', '>', $lastId)
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->limit(self::$chunkSize)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            $lastId = (int) end($ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $changed += DB::update(
                'UPDATE posts p SET p.score = '.self::SCORE_SQL."
                 WHERE p.id IN ({$placeholders})",
                [$this->decayHours, ...$ids],
            );
        }

        return $changed;
    }

    /**
     * Counts published posts whose stored score differs from the recomputed value.
     */
    public function countPostsWithStaleScore(): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS n FROM posts p
             WHERE p.status = \'published\'
             AND p.deleted_at IS NULL
             AND p.score <> '.self::SCORE_SQL,
            [$this->decayHours],
        );

        return (int) ($row->n ?? 0);
    }

    /**
     * Recomputes a single published post's score.
     */
    public function recalculatePostScore(int $postId): void
    {
        DB::update(
            'UPDATE posts p SET p.score = '.self::SCORE_SQL."
             WHERE p.id = ?
             AND p.status = 'published'
             AND p.deleted_at IS NULL",
            [$this->decayHours, $postId],
        );
    }

    /**
     * Top posts by score adjusted for time decay, cached.
     */
    public function getTrendingPosts(int $limit = 10, int $hoursWindow = 24): array
    {
        return Cache::remember($this->key("trending:{$limit}:{$hoursWindow}"), $this->ttlFor('trending'), function () use ($limit, $hoursWindow) {
            $sinceTime = date('Y-m-d H:i:s', time() - ($hoursWindow * 3600));

            return DB::select(
                "SELECT p.*, u.username, u.display_name, u.avatar_url, u.anonymized_at,
                    (p.score - (TIMESTAMPDIFF(HOUR, p.published_at, NOW()) / ?)) as adjusted_score
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.published_at >= ?
                ORDER BY adjusted_score DESC LIMIT ?",
                [$this->decayHours, $sinceTime, $limit],
            );
        });
    }

    /**
     * Recently active posts by reaction and comment activity, cached.
     */
    public function getHotPosts(int $limit = 10): array
    {
        return Cache::remember($this->key("hot:{$limit}"), $this->ttlFor('hot'), function () use ($limit) {
            $sinceTime = date('Y-m-d H:i:s', time() - (6 * 3600));

            return DB::select(
                "SELECT p.*, u.username, u.display_name, u.avatar_url, u.anonymized_at,
                    (p.reaction_count + p.comment_count) as activity_score
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.updated_at >= ?
                ORDER BY activity_score DESC LIMIT ?",
                [$sinceTime, $limit],
            );
        });
    }

    private function pinnedOrder(string $method): string
    {
        return $method === HomeFeedTabs::defaultMethod() ? 'p.is_pinned DESC, ' : '';
    }

    private function pinnedKey(string $pinned): string
    {
        return $pinned === '' ? 'n' : 'p';
    }

    /**
     * Top scored posts from the last 7 days, cached.
     */
    public function getPopularPosts(int $limit = 25, int $offset = 0): array
    {
        $pinned = $this->pinnedOrder('getPopularPosts');

        return Cache::remember($this->key("popular:{$limit}:{$offset}:{$this->pinnedKey($pinned)}"), $this->ttlFor('getPopularPosts'), function () use ($limit, $offset, $pinned) {
            $sevenDaysAgo = date('Y-m-d H:i:s', time() - (7 * 24 * 3600));

            return DB::select(
                "SELECT p.*, u.username, u.display_name, u.avatar_url, u.anonymized_at, p.score as reaction_score
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.published_at >= ?
                ORDER BY {$pinned}
                    CASE WHEN p.score > 0 THEN 1 WHEN p.score = 0 THEN 2 ELSE 3 END ASC,
                    p.score DESC, p.published_at DESC, p.id DESC
                LIMIT ? OFFSET ?",
                [$sevenDaysAgo, $limit, $offset],
            );
        });
    }

    /**
     * Most recently published posts, cached.
     */
    public function getAllPosts(int $limit = 25, int $offset = 0): array
    {
        $pinned = $this->pinnedOrder('getAllPosts');

        return Cache::remember($this->key("all:{$limit}:{$offset}:{$this->pinnedKey($pinned)}"), $this->ttlFor('getAllPosts'), function () use ($limit, $offset, $pinned) {
            return DB::select(
                "SELECT p.*, u.username, u.display_name, u.avatar_url, u.anonymized_at
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL
                ORDER BY {$pinned}p.published_at DESC, p.id DESC
                LIMIT ? OFFSET ?",
                [$limit, $offset],
            );
        });
    }

    /**
     * Posts with recent comment activity, cached.
     */
    public function getPostsByRecentComments(int $limit = 25, int $offset = 0): array
    {
        $pinned = $this->pinnedOrder('getPostsByRecentComments');

        return Cache::remember($this->key("recent_comments:{$limit}:{$offset}:{$this->pinnedKey($pinned)}"), $this->ttlFor('getPostsByRecentComments'), function () use ($limit, $offset, $pinned) {
            return DB::select(
                "SELECT p.*, u.username, u.display_name, u.avatar_url, u.anonymized_at
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL AND p.comment_count > 0
                ORDER BY {$pinned}p.last_comment_at DESC, p.id DESC
                LIMIT ? OFFSET ?",
                [$limit, $offset],
            );
        });
    }

    /**
     * Posts with comments open, ranked by reactions, cached.
     */
    public function getPostsByReactionsOnly(int $limit = 25, int $offset = 0): array
    {
        $pinned = $this->pinnedOrder('getPostsByReactionsOnly');

        return Cache::remember($this->key("by_reactions:{$limit}:{$offset}:{$this->pinnedKey($pinned)}"), $this->ttlFor('getPostsByReactionsOnly'), function () use ($limit, $offset, $pinned) {
            return DB::select(
                "SELECT p.*, u.username, u.display_name, u.avatar_url, u.anonymized_at
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL AND p.allow_comments = 1
                ORDER BY {$pinned}p.reaction_score DESC, p.reaction_count DESC, p.published_at DESC, p.id DESC
                LIMIT ? OFFSET ?",
                [$limit, $offset],
            );
        });
    }

    /**
     * Posts with comments open, ranked by comment count, cached.
     */
    public function getPostsByCommentCount(int $limit = 25, int $offset = 0): array
    {
        $pinned = $this->pinnedOrder('getPostsByCommentCount');

        return Cache::remember($this->key("by_comments:{$limit}:{$offset}:{$this->pinnedKey($pinned)}"), $this->ttlFor('getPostsByCommentCount'), function () use ($limit, $offset, $pinned) {
            return DB::select(
                "SELECT p.*, u.username, u.display_name, u.avatar_url, u.anonymized_at
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL AND p.allow_comments = 1
                ORDER BY {$pinned}p.comment_count DESC, p.published_at DESC, p.id DESC
                LIMIT ? OFFSET ?",
                [$limit, $offset],
            );
        });
    }

    /**
     * Published posts with no comments and comments open, cached.
     */
    public function getPostsWithoutComments(int $limit = 25, int $offset = 0): array
    {
        $pinned = $this->pinnedOrder('getPostsWithoutComments');

        return Cache::remember($this->key("no_comments:{$limit}:{$offset}:{$this->pinnedKey($pinned)}"), $this->ttlFor('getPostsWithoutComments'), function () use ($limit, $offset, $pinned) {
            return DB::select(
                "SELECT p.*, u.username, u.display_name, u.avatar_url, u.anonymized_at
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL AND p.comment_count = 0 AND p.allow_comments = 1
                ORDER BY {$pinned}p.published_at DESC, p.id DESC
                LIMIT ? OFFSET ?",
                [$limit, $offset],
            );
        });
    }

    /**
     * Most recent visible comments, cached.
     */
    public function getAllComments(int $limit = 25, int $offset = 0): array
    {
        return Cache::remember($this->key("all_comments:{$limit}:{$offset}"), $this->ttlFor('getAllComments'), function () use ($limit, $offset) {
            return DB::select(
                "SELECT c.*, u.username, u.display_name, u.avatar_url, u.anonymized_at,
                    p.title as post_title, p.hashid as post_hashid, p.slug as post_slug
                FROM comments c
                JOIN users u ON c.user_id = u.id
                JOIN posts p ON c.post_id = p.id
                WHERE c.status = 'visible' AND c.deleted_at IS NULL AND c.version_of IS NULL
                    AND p.deleted_at IS NULL AND p.version_of IS NULL
                ORDER BY c.created_at DESC, c.id DESC
                LIMIT ? OFFSET ?",
                [$limit, $offset],
            );
        });
    }

    /**
     * Total visible comment count, cached.
     */
    public function allCommentsTotal(): int
    {
        return Cache::remember($this->key('count:all_comments'), $this->ttlFor('getAllComments'), function () {
            $row = DB::selectOne(
                "SELECT COUNT(*) AS n FROM comments c
                JOIN posts p ON c.post_id = p.id
                WHERE c.status = 'visible' AND c.deleted_at IS NULL AND c.version_of IS NULL
                    AND p.deleted_at IS NULL AND p.version_of IS NULL",
            );

            return (int) ($row->n ?? 0);
        });
    }

    /**
     * Paginates all comments.
     */
    public function paginateComments(int $perPage, int $page): LengthAwarePaginator
    {
        $offset = ($page - 1) * $perPage;
        $items = $this->getAllComments($perPage, $offset);

        return new LengthAwarePaginator(
            $items,
            $this->allCommentsTotal(),
            $perPage,
            $page,
            [
                'path' => Url::paginationPath(),
                'pageName' => 'page',
            ],
        );
    }

    private function key(string $suffix): string
    {
        return 'ranking:g'.app(FeedGeneration::class)->current().':'.$suffix;
    }

    private function ttlFor(string $method): int
    {
        $map = (array) config('orbita.cache.ranking_ttl', []);

        return (int) ($map[$method] ?? $map['default'] ?? 60);
    }

    private function feedCountConfig(string $method): array
    {
        $sevenDaysAgo = date('Y-m-d H:i:s', time() - (7 * 24 * 3600));

        return match ($method) {
            'getPopularPosts' => [
                'where' => "p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.published_at >= ?",
                'bindings' => [$sevenDaysAgo],
            ],
            'getPostsWithoutComments' => [
                'where' => "p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL AND p.comment_count = 0 AND p.allow_comments = 1",
                'bindings' => [],
            ],
            'getPostsByRecentComments' => [
                'where' => "p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL AND p.comment_count > 0",
                'bindings' => [],
            ],
            'getAllPosts' => [
                'where' => "p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL",
                'bindings' => [],
            ],
            'getPostsByReactionsOnly', 'getPostsByCommentCount' => [
                'where' => "p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL AND p.allow_comments = 1",
                'bindings' => [],
            ],
            default => [
                'where' => "p.status IN ('published', 'closed') AND p.deleted_at IS NULL AND p.version_of IS NULL",
                'bindings' => [],
            ],
        };
    }

    /**
     * Total post count for a feed method, cached.
     */
    public function feedTotal(string $method): int
    {
        $config = $this->feedCountConfig($method);

        return Cache::remember($this->key("count:{$method}"), $this->ttlFor($method), function () use ($config) {
            $row = DB::selectOne(
                "SELECT COUNT(*) AS n FROM posts p WHERE {$config['where']}",
                $config['bindings'],
            );

            return (int) ($row->n ?? 0);
        });
    }

    /**
     * Paginates a feed by method name.
     */
    public function paginatePosts(string $method, int $perPage, int $page): LengthAwarePaginator
    {
        $offset = ($page - 1) * $perPage;

        $items = $this->{$method}($perPage, $offset);

        return new LengthAwarePaginator(
            $items,
            $this->feedTotal($method),
            $perPage,
            $page,
            [
                'path' => Url::paginationPath(),
                'pageName' => 'page',
            ],
        );
    }
}
