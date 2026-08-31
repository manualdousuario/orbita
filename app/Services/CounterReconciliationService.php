<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recomputes denormalized counters and posts.score from authoritative tables.
 */
class CounterReconciliationService
{
    public function __construct(private readonly RankingService $ranking) {}

    /** @return array<string, int> */
    public function reconcileAll(): array
    {
        return [
            'posts.comment_count' => $this->reconcilePostCommentCount(),
            'posts.last_comment_at' => $this->reconcilePostLastCommentAt(),
            'posts.reaction_count' => $this->reconcilePostReactionCount(),
            'posts.reaction_score' => $this->reconcilePostReactionScore(),
            'comments.reaction_count' => $this->reconcileCommentReactionCount(),
            'comments.score' => $this->reconcileCommentScore(),
            'terms.usage_count' => $this->reconcileTermUsageCount(),
            // Advances the time decay; returns rows actually changed.
            'posts.score' => $this->ranking->recalculateAllScores(),
        ];
    }

    /** @return array<string, int> */
    public function previewReconcileAll(): array
    {
        return [
            'posts.comment_count' => $this->previewPostCommentCount(),
            'posts.last_comment_at' => $this->previewPostLastCommentAt(),
            'posts.reaction_count' => $this->previewPostReactionCount(),
            'posts.reaction_score' => $this->previewPostReactionScore(),
            'comments.reaction_count' => $this->previewCommentReactionCount(),
            'comments.score' => $this->previewCommentScore(),
            'terms.usage_count' => $this->previewTermUsageCount(),
            // Must mirror reconcileAll(), or --dry-run under-reports what a real run will do.
            'posts.score' => $this->ranking->countPostsWithStaleScore(),
        ];
    }

    public function countExpiredOembedCache(): int
    {
        return (int) DB::scalar('SELECT COUNT(*) FROM oembed_cache WHERE expires_at IS NOT NULL AND expires_at < NOW()');
    }

    public function previewPostCommentCount(): int
    {
        return (int) DB::scalar(
            "SELECT COUNT(*) FROM posts p
             LEFT JOIN (
                 SELECT post_id, COUNT(*) AS c
                 FROM comments
                 WHERE status = 'visible' AND deleted_at IS NULL
                 GROUP BY post_id
             ) c ON c.post_id = p.id
             WHERE p.comment_count <> COALESCE(c.c, 0)",
        );
    }

    public function previewPostReactionScore(): int
    {
        return (int) DB::scalar(
            "SELECT COUNT(*) FROM posts p
             LEFT JOIN (
                 SELECT ur.reactable_id AS post_id, COALESCE(SUM(rt.score), 0) AS s
                 FROM user_reactions ur
                 JOIN reaction_types rt ON ur.reaction_type_id = rt.id
                 WHERE ur.reactable_type = 'post'
                 GROUP BY ur.reactable_id
             ) r ON r.post_id = p.id
             WHERE p.reaction_score <> COALESCE(r.s, 0)",
        );
    }

    public function previewPostLastCommentAt(): int
    {
        return (int) DB::scalar(
            "SELECT COUNT(*) FROM posts p
             LEFT JOIN (
                 SELECT post_id, MAX(created_at) AS last_at
                 FROM comments
                 WHERE status = 'visible' AND deleted_at IS NULL AND version_of IS NULL
                 GROUP BY post_id
             ) c ON c.post_id = p.id
             WHERE NOT (p.last_comment_at <=> c.last_at)",
        );
    }

    public function previewPostReactionCount(): int
    {
        return (int) DB::scalar(
            "SELECT COUNT(*) FROM posts p
             LEFT JOIN (
                 SELECT reactable_id, COUNT(*) AS c
                 FROM user_reactions
                 WHERE reactable_type = 'post'
                 GROUP BY reactable_id
             ) r ON r.reactable_id = p.id
             WHERE p.reaction_count <> COALESCE(r.c, 0)",
        );
    }

    public function previewCommentReactionCount(): int
    {
        return (int) DB::scalar(
            "SELECT COUNT(*) FROM comments c
             LEFT JOIN (
                 SELECT reactable_id, COUNT(*) AS cnt
                 FROM user_reactions
                 WHERE reactable_type = 'comment'
                 GROUP BY reactable_id
             ) r ON r.reactable_id = c.id
             WHERE c.reaction_count <> COALESCE(r.cnt, 0)",
        );
    }

    public function previewCommentScore(): int
    {
        return (int) DB::scalar(
            "SELECT COUNT(*) FROM comments c
             LEFT JOIN (
                 SELECT ur.reactable_id AS comment_id, COALESCE(SUM(rt.score), 0) AS s
                 FROM user_reactions ur
                 JOIN reaction_types rt ON ur.reaction_type_id = rt.id
                 WHERE ur.reactable_type = 'comment'
                 GROUP BY ur.reactable_id
             ) r ON r.comment_id = c.id
             WHERE c.score <> COALESCE(r.s, 0)",
        );
    }

    public function previewTermUsageCount(): int
    {
        return (int) DB::scalar(
            'SELECT COUNT(*) FROM terms t
             LEFT JOIN (
                 SELECT term_id, COUNT(*) AS c
                 FROM term_relationships
                 GROUP BY term_id
             ) r ON r.term_id = t.id
             WHERE t.usage_count <> COALESCE(r.c, 0)',
        );
    }

    /** MySQL-targeted bulk UPDATE ... LEFT JOIN. */
    public function reconcilePostCommentCount(): int
    {
        $rows = DB::update(
            "UPDATE posts p
             LEFT JOIN (
                 SELECT post_id, COUNT(*) AS c
                 FROM comments
                 WHERE status = 'visible' AND deleted_at IS NULL
                 GROUP BY post_id
             ) c ON c.post_id = p.id
             SET p.comment_count = COALESCE(c.c, 0)
             WHERE p.comment_count <> COALESCE(c.c, 0)",
        );
        Log::info('Reconciled posts.comment_count', ['rows' => $rows]);

        return $rows;
    }

    public function reconcilePostReactionScore(): int
    {
        $rows = DB::update(
            "UPDATE posts p
             LEFT JOIN (
                 SELECT ur.reactable_id AS post_id, COALESCE(SUM(rt.score), 0) AS s
                 FROM user_reactions ur
                 JOIN reaction_types rt ON ur.reaction_type_id = rt.id
                 WHERE ur.reactable_type = 'post'
                 GROUP BY ur.reactable_id
             ) r ON r.post_id = p.id
             SET p.reaction_score = COALESCE(r.s, 0)
             WHERE p.reaction_score <> COALESCE(r.s, 0)",
        );
        Log::info('Reconciled posts.reaction_score', ['rows' => $rows]);

        return $rows;
    }

    public function reconcilePostLastCommentAt(): int
    {
        $rows = DB::update(
            "UPDATE posts p
             LEFT JOIN (
                 SELECT post_id, MAX(created_at) AS last_at
                 FROM comments
                 WHERE status = 'visible' AND deleted_at IS NULL AND version_of IS NULL
                 GROUP BY post_id
             ) c ON c.post_id = p.id
             SET p.last_comment_at = c.last_at
             WHERE NOT (p.last_comment_at <=> c.last_at)",
        );
        Log::info('Reconciled posts.last_comment_at', ['rows' => $rows]);

        return $rows;
    }

    /** MySQL-targeted bulk UPDATE ... LEFT JOIN. */
    public function reconcilePostReactionCount(): int
    {
        $rows = DB::update(
            "UPDATE posts p
             LEFT JOIN (
                 SELECT reactable_id, COUNT(*) AS c
                 FROM user_reactions
                 WHERE reactable_type = 'post'
                 GROUP BY reactable_id
             ) r ON r.reactable_id = p.id
             SET p.reaction_count = COALESCE(r.c, 0)
             WHERE p.reaction_count <> COALESCE(r.c, 0)",
        );
        Log::info('Reconciled posts.reaction_count', ['rows' => $rows]);

        return $rows;
    }

    /** MySQL-targeted bulk UPDATE ... LEFT JOIN. */
    public function reconcileCommentReactionCount(): int
    {
        $rows = DB::update(
            "UPDATE comments c
             LEFT JOIN (
                 SELECT reactable_id, COUNT(*) AS cnt
                 FROM user_reactions
                 WHERE reactable_type = 'comment'
                 GROUP BY reactable_id
             ) r ON r.reactable_id = c.id
             SET c.reaction_count = COALESCE(r.cnt, 0)
             WHERE c.reaction_count <> COALESCE(r.cnt, 0)",
        );
        Log::info('Reconciled comments.reaction_count', ['rows' => $rows]);

        return $rows;
    }

    public function reconcileCommentScore(): int
    {
        $rows = DB::update(
            "UPDATE comments c
             LEFT JOIN (
                 SELECT ur.reactable_id AS comment_id, COALESCE(SUM(rt.score), 0) AS s
                 FROM user_reactions ur
                 JOIN reaction_types rt ON ur.reaction_type_id = rt.id
                 WHERE ur.reactable_type = 'comment'
                 GROUP BY ur.reactable_id
             ) r ON r.comment_id = c.id
             SET c.score = COALESCE(r.s, 0)
             WHERE c.score <> COALESCE(r.s, 0)",
        );
        Log::info('Reconciled comments.score', ['rows' => $rows]);

        return $rows;
    }

    /** MySQL-targeted bulk UPDATE ... LEFT JOIN. */
    public function reconcileTermUsageCount(): int
    {
        $rows = DB::update(
            'UPDATE terms t
             LEFT JOIN (
                 SELECT term_id, COUNT(*) AS c
                 FROM term_relationships
                 GROUP BY term_id
             ) r ON r.term_id = t.id
             SET t.usage_count = COALESCE(r.c, 0)
             WHERE t.usage_count <> COALESCE(r.c, 0)',
        );
        Log::info('Reconciled terms.usage_count', ['rows' => $rows]);

        return $rows;
    }

    public function purgeExpiredOembedCache(): int
    {
        $rows = DB::delete('DELETE FROM oembed_cache WHERE expires_at IS NOT NULL AND expires_at < NOW()');
        Log::info('Purged expired oembed_cache rows', ['rows' => $rows]);

        return $rows;
    }
}
