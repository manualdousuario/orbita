<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CounterReconciliationService;
use App\Services\RankingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Recalculates reaction and ranking scores after a reaction type change.
 */
class ResyncReactionScores implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Long enough for a full pass over posts/comments, short enough to unblock a stuck run. */
    public int $uniqueFor = 600;

    public function handle(CounterReconciliationService $reconcile, RankingService $ranking): void
    {
        $rows = [
            'posts.reaction_score' => $reconcile->reconcilePostReactionScore(),
            'comments.score' => $reconcile->reconcileCommentScore(),
            'posts.score' => $ranking->recalculateAllScores(),
        ];

        Log::info('Resynced reaction scores after a reaction type changed', $rows);
    }
}
