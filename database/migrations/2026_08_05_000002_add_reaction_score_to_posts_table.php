<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->integer('reaction_score')->default(0)->after('reaction_count');
            $table->index(
                ['is_pinned', 'reaction_score', 'reaction_count', 'published_at', 'id'],
                'posts_pinned_reaction_score_index',
            );
        });

        DB::statement(
            "UPDATE posts p
             LEFT JOIN (
                 SELECT ur.reactable_id AS post_id, COALESCE(SUM(rt.score), 0) AS s
                 FROM user_reactions ur
                 JOIN reaction_types rt ON ur.reaction_type_id = rt.id
                 WHERE ur.reactable_type = 'post'
                 GROUP BY ur.reactable_id
             ) r ON r.post_id = p.id
             SET p.reaction_score = COALESCE(r.s, 0)"
        );
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_pinned_reaction_score_index');
            $table->dropColumn('reaction_score');
        });
    }
};
