<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ReportCreated;
use App\Models\Comment;
use App\Models\Moderation;
use App\Models\Post;
use App\Models\Report;
use App\Support\ModerationLogger;

/**
 * Handles user reports, one per user per target.
 */
class ReportService
{
    public const TYPES = ['post', 'comment'];

    /**
     * Registers a report, returning the new or existing one, or null for invalid input.
     */
    public function report(int $userId, string $reportableType, int $reportableId, ?string $reason = null): ?Report
    {
        if (! $this->isValidType($reportableType)) {
            return null;
        }

        $target = $this->findTarget($reportableType, $reportableId);
        if ($target === null) {
            return null;
        }

        $report = Report::firstOrCreate(
            [
                'user_id' => $userId,
                'reportable_type' => $reportableType,
                'reportable_id' => $reportableId,
            ],
            ['reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null],
        );

        if ($report->wasRecentlyCreated) {
            ReportCreated::dispatch($report, $target);
        }

        return $report;
    }

    public function isValidType(string $reportableType): bool
    {
        return in_array($reportableType, self::TYPES, true);
    }

    public function findTarget(string $reportableType, int $reportableId): Post|Comment|null
    {
        return $reportableType === 'post'
            ? Post::find($reportableId)
            : Comment::find($reportableId);
    }

    public function hasReported(int $userId, string $reportableType, int $reportableId): bool
    {
        return Report::query()
            ->where('user_id', $userId)
            ->where('reportable_type', $reportableType)
            ->where('reportable_id', $reportableId)
            ->exists();
    }

    /**
     * Ids the user has already reported, batched in one query.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function reportedIdsFor(?int $userId, string $reportableType, array $ids): array
    {
        if ($userId === null || $ids === []) {
            return [];
        }

        return Report::query()
            ->where('user_id', $userId)
            ->where('reportable_type', $reportableType)
            ->whereIn('reportable_id', $ids)
            ->pluck('reportable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function countFor(string $reportableType, int $reportableId): int
    {
        return Report::query()
            ->where('reportable_type', $reportableType)
            ->where('reportable_id', $reportableId)
            ->count();
    }

    public function autoHideIfThresholdReached(Post|Comment $target, int $reportCount): bool
    {
        $threshold = (int) config('orbita.moderation.auto_hide_threshold', 3);

        if ($threshold <= 0 || $reportCount < $threshold) {
            return false;
        }

        $type = $target instanceof Post ? 'post' : 'comment';
        $eligible = $target instanceof Post ? ['published', 'closed'] : ['visible'];

        if (! in_array((string) $target->status, $eligible, true)) {
            return false;
        }

        $alreadyFired = Moderation::query()
            ->where('action', 'auto_hide')
            ->where('target_type', $type)
            ->where('target_id', $target->id)
            ->exists();

        if ($alreadyFired) {
            return false;
        }

        $previousStatus = (string) $target->status;
        $target->update(['status' => 'hidden']);

        ModerationLogger::system(
            'auto_hide',
            $type,
            (int) $target->id,
            "Ocultado automaticamente após {$reportCount} denúncias",
            ['reports' => $reportCount, 'threshold' => $threshold, 'previous_status' => $previousStatus],
        );

        return true;
    }

    public function markRead(Report $report, int $moderatorId): void
    {
        $report->update(['read_at' => now(), 'read_by' => $moderatorId]);
    }

    public function markUnread(Report $report): void
    {
        $report->update(['read_at' => null, 'read_by' => null]);
    }
}
