<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds last_comment_at to posts and backfills it from visible comments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('last_comment_at')->nullable()->after('published_at');
            $table->index(['is_pinned', 'last_comment_at', 'id'], 'posts_pinned_last_comment_index');
            $table->index(['is_pinned', 'comment_count', 'published_at', 'id'], 'posts_pinned_comments_index');
        });

        // Backfill with exactly the predicate the subquery used, so the feed does not shift.
        DB::statement(
            "UPDATE posts p
             SET p.last_comment_at = (
                 SELECT MAX(c.created_at) FROM comments c
                 WHERE c.post_id = p.id
                   AND c.deleted_at IS NULL
                   AND c.version_of IS NULL
                   AND c.status = 'visible'
             )
             WHERE p.comment_count > 0"
        );
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_pinned_last_comment_index');
            $table->dropIndex('posts_pinned_comments_index');
            $table->dropColumn('last_comment_at');
        });
    }
};
