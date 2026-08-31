<?php

namespace App\Observers;

use App\Models\Comment;
use App\Services\RankingService;
use App\Support\FeedGeneration;
use Illuminate\Support\Facades\DB;

/**
 * Keeps posts.comment_count, last_comment_at and score in sync with comments.
 */
class CommentObserver
{
    public function created(Comment $comment): void
    {
        // Revision snapshots are never visible; they cannot move the counter.
        if ($comment->version_of !== null) {
            return;
        }

        $this->sync((int) $comment->post_id);
    }

    public function deleted(Comment $comment): void
    {
        $this->sync((int) $comment->post_id);
    }

    public function restored(Comment $comment): void
    {
        $this->sync((int) $comment->post_id);
    }

    /** Only a visibility change can move the counter; skip the write for score/content edits. */
    public function updated(Comment $comment): void
    {
        if ($comment->wasChanged('status') || $comment->wasChanged('deleted_at')) {
            $this->sync((int) $comment->post_id);
        }
    }

    private function sync(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }

        DB::update(
            "UPDATE posts p
             SET p.comment_count = (
                     SELECT COUNT(*) FROM comments c
                     WHERE c.post_id = p.id AND c.status = 'visible' AND c.deleted_at IS NULL
                 ),
                 p.last_comment_at = (
                     SELECT MAX(c.created_at) FROM comments c
                     WHERE c.post_id = p.id
                       AND c.status = 'visible'
                       AND c.deleted_at IS NULL
                       AND c.version_of IS NULL
                 )
             WHERE p.id = ?",
            [$postId],
        );

        // Counter and score move together; recompute now, cron re-applies decay.
        app(RankingService::class)->recalculatePostScore($postId);

        app(FeedGeneration::class)->bump();
    }
}
