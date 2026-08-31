<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deletes stale read and unread notifications.
 */
class NotificationsCleanCommand extends Command
{
    protected $signature = 'orbita:notifications-clean
        {--read-days= : Delete READ notifications whose read_at is older than this many days (default: 30)}
        {--unread-days= : Delete UNREAD notifications whose created_at is older than this many days (default: 90)}
        {--dry-run : Report how many rows would be deleted without changing anything}
        {--stats : Print notification metrics instead of cleaning anything}';

    protected $description = 'Delete stale read/unread notifications (with optional --stats view)';

    public function handle(): int
    {
        if ($this->option('stats')) {
            return $this->showStats();
        }

        $readDays = (int) ($this->option('read-days') ?: config('orbita.notifications.read_retention_days'));
        $unreadDays = (int) ($this->option('unread-days') ?: config('orbita.notifications.unread_retention_days'));

        $readCutoff = Carbon::now()->subDays($readDays);
        $unreadCutoff = Carbon::now()->subDays($unreadDays);

        $this->info('Cleaning old notifications...');

        $readQuery = Notification::query()
            ->where('is_read', true)
            ->where('read_at', '<', $readCutoff);

        $unreadQuery = Notification::query()
            ->where('is_read', false)
            ->where('created_at', '<', $unreadCutoff);

        if ($this->option('dry-run')) {
            $this->comment('Mode: DRY RUN (no changes will be made)');
            $this->line("Would delete {$readQuery->count()} read notifications older than {$readDays} days");
            $this->line("Would delete {$unreadQuery->count()} unread notifications older than {$unreadDays} days");
            $this->comment('Run without --dry-run to apply changes.');

            return self::SUCCESS;
        }

        $deletedRead = $this->deleteInChunks($readQuery);
        $this->info("Deleted {$deletedRead} read notifications older than {$readDays} days");

        $deletedUnread = $this->deleteInChunks($unreadQuery);
        $this->info("Deleted {$deletedUnread} unread notifications older than {$unreadDays} days");

        $this->line('Total cleaned: '.($deletedRead + $deletedUnread).' notifications');

        return self::SUCCESS;
    }

    private function deleteInChunks($query, int $chunkSize = 1000): int
    {
        $deleted = 0;

        do {
            $batch = (clone $query)->limit($chunkSize)->delete();
            $deleted += $batch;
        } while ($batch === $chunkSize);

        return $deleted;
    }

    private function showStats(): int
    {
        $this->info('Notification Statistics');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total', Notification::query()->count()],
                ['Unread', Notification::query()->where('is_read', false)->count()],
                ['Read', Notification::query()->where('is_read', true)->count()],
            ],
        );

        $byType = DB::table('notifications')
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->get();

        if ($byType->isNotEmpty()) {
            $this->line('');
            $this->info('By type:');
            $this->table(
                ['Type', 'Count'],
                $byType->map(fn ($r) => [$r->type, $r->count])->all(),
            );
        }

        $this->line('');
        $this->info('Eligible for cleanup:');
        $this->table(
            ['Category', 'Count'],
            [
                ['Read (>'.config('orbita.notifications.read_retention_days').' days)', Notification::query()
                    ->where('is_read', true)
                    ->where('read_at', '<', Carbon::now()->subDays((int) config('orbita.notifications.read_retention_days')))
                    ->count()],
                ['Unread (>'.config('orbita.notifications.unread_retention_days').' days)', Notification::query()
                    ->where('is_read', false)
                    ->where('created_at', '<', Carbon::now()->subDays((int) config('orbita.notifications.unread_retention_days')))
                    ->count()],
            ],
        );

        return self::SUCCESS;
    }
}
