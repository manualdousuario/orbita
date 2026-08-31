<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a report is created.
 */
class ReportCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Report $report,
        public readonly Post|Comment $target,
    ) {}
}
