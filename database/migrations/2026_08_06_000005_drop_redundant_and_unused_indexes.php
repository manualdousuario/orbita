<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops redundant and unused database indexes.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private const REDUNDANT_PREFIXES = [
        'comment_media' => ['comment_media_comment_id_index'],
        'media_relationship' => ['media_relationship_post_id_index'],
        'posts' => ['posts_is_pinned_index', 'posts_status_index'],
        'terms' => ['terms_taxonomy_index'],
        'user_reactions' => ['user_reactions_user_id_index'],
    ];

    /** @var array<string, array<int, string>> */
    private const UNUSED = [
        'posts' => ['posts_slug_index'],
        'comments' => ['comments_nesting_level_index'],
    ];

    public function up(): void
    {
        foreach ([self::REDUNDANT_PREFIXES, self::UNUSED] as $group) {
            foreach ($group as $table => $indexes) {
                Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                    foreach ($indexes as $index) {
                        $blueprint->dropIndex($index);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('comment_media', fn (Blueprint $t) => $t->index('comment_id', 'comment_media_comment_id_index'));
        Schema::table('media_relationship', fn (Blueprint $t) => $t->index('post_id', 'media_relationship_post_id_index'));
        Schema::table('terms', fn (Blueprint $t) => $t->index('taxonomy', 'terms_taxonomy_index'));
        Schema::table('user_reactions', fn (Blueprint $t) => $t->index('user_id', 'user_reactions_user_id_index'));
        Schema::table('comments', fn (Blueprint $t) => $t->index('nesting_level', 'comments_nesting_level_index'));

        Schema::table('posts', function (Blueprint $table) {
            $table->index('is_pinned', 'posts_is_pinned_index');
            $table->index('status', 'posts_status_index');
            $table->index('slug', 'posts_slug_index');
        });
    }
};
