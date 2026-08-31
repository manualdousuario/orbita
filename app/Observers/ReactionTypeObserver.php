<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ResyncReactionScores;
use App\Models\ReactionType;

/**
 * Dispatches reaction-score resync when a type's score changes or it is deleted.
 */
class ReactionTypeObserver
{
    public function updated(ReactionType $type): void
    {
        if ($type->wasChanged('score')) {
            ResyncReactionScores::dispatch();
        }
    }

    /** Deleting drops the type out of the JOIN, so every sum that included it falls. */
    public function deleted(ReactionType $type): void
    {
        ResyncReactionScores::dispatch();
    }
}
