<?php

use App\Services\RankingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the `report` reaction type and re-syncs affected aggregates.
 */
return new class extends Migration
{
    public function up(): void
    {
        $typeId = DB::table('reaction_types')->where('slug', 'report')->value('id');
        if ($typeId === null) {
            return;
        }

        $targets = DB::table('user_reactions')
            ->where('reaction_type_id', $typeId)
            ->get(['reactable_type', 'reactable_id'])
            ->unique(fn ($row) => $row->reactable_type.':'.$row->reactable_id);

        DB::table('user_reactions')->where('reaction_type_id', $typeId)->delete();
        DB::table('reaction_types')->where('id', $typeId)->delete();

        // Mirror ReactionService::syncReactionAggregates(): comments.score is the reaction sum; posts.score is RankingService-owned.
        foreach ($targets as $target) {
            $row = DB::table('user_reactions as ur')
                ->join('reaction_types as rt', 'ur.reaction_type_id', '=', 'rt.id')
                ->where('ur.reactable_type', $target->reactable_type)
                ->where('ur.reactable_id', $target->reactable_id)
                ->selectRaw('COUNT(*) as total, COALESCE(SUM(rt.score), 0) as score')
                ->first();

            if ($target->reactable_type === 'comment') {
                DB::table('comments')->where('id', $target->reactable_id)->update([
                    'reaction_count' => (int) $row->total,
                    'score' => (int) $row->score,
                ]);
            } else {
                DB::table('posts')->where('id', $target->reactable_id)->update([
                    'reaction_count' => (int) $row->total,
                ]);
                app(RankingService::class)->recalculatePostScore((int) $target->reactable_id);
            }
        }
    }

    public function down(): void
    {
        // No-op: deleted report reactions cannot be restored.
    }
};
