<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PostService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Closes posts that have gone inactive.
 */
class PostsCloseInactiveCommand extends Command
{
    protected $signature = 'orbita:posts-close-inactive
        {--days= : Inactivity threshold in days (defaults to config orbita.posts.close_inactive_days)}
        {--dry-run : Report how many posts would close without changing anything}
        {--stats : Print post metrics instead of closing anything}';

    protected $description = 'Close posts inactive for the configured number of days (status -> closed)';

    public function handle(PostService $posts): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('orbita.posts.close_inactive_days', 30);

        if ($this->option('stats')) {
            return $this->showStats($posts);
        }

        if ($days <= 0) {
            $this->info('Automatic closure disabled (day limit = 0).');

            return self::SUCCESS;
        }

        $this->info('Closing Inactive Posts');
        $this->line("Inactivity threshold: {$days} days");

        if ($this->option('dry-run')) {
            $this->comment('Mode: DRY RUN (no changes will be made)');
            $count = $posts->inactivePostsQuery($days)->count();

            if ($count === 0) {
                $this->info('No inactive posts found.');

                return self::SUCCESS;
            }

            $this->line("Would close {$count} inactive post(s).");
            $this->comment('Run without --dry-run to close these posts.');

            return self::SUCCESS;
        }

        $closed = $posts->closeInactivePosts($days);

        if ($closed === 0) {
            $this->comment('No inactive posts found to close.');
        } else {
            $this->info("Closed {$closed} inactive post(s).");
        }

        return self::SUCCESS;
    }

    private function showStats(PostService $posts): int
    {
        $this->info('Post Statistics');

        $total = (int) DB::table('posts')
            ->whereNull('version_of')
            ->whereNull('deleted_at')
            ->count();

        $this->line("Total posts: {$total}");

        $byStatus = DB::table('posts')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereNull('version_of')
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        if ($byStatus->isNotEmpty()) {
            $this->line('');
            $this->info('By status:');
            $this->table(
                ['Status', 'Count'],
                $byStatus->map(fn ($r) => [$r->status, $r->count])->all(),
            );
        }

        $this->line('');
        $this->info('Inactive posts:');
        $this->table(
            ['Threshold', 'Count'],
            [
                ['30 days', $posts->inactivePostsQuery(30)->count()],
                ['60 days', $posts->inactivePostsQuery(60)->count()],
                ['90 days', $posts->inactivePostsQuery(90)->count()],
            ],
        );

        return self::SUCCESS;
    }
}
