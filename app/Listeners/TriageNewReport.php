<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReportCreated;
use App\Services\NotificationService;
use App\Services\ReportService;

/**
 * Auto-hides reports past the threshold and notifies staff.
 */
class TriageNewReport
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly NotificationService $notifications,
    ) {}

    public function handle(ReportCreated $event): void
    {
        $count = $this->reports->countFor(
            (string) $event->report->reportable_type,
            (int) $event->report->reportable_id,
        );

        $autoHidden = $this->reports->autoHideIfThresholdReached($event->target, $count);

        $this->notifications->notifyStaffOfReport($event->report, $event->target, $count, $autoHidden);
    }
}
