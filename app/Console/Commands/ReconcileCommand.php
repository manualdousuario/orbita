<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CounterReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reconciles denormalized counters and purges expired cache rows.
 */
class ReconcileCommand extends Command
{
    protected $signature = 'orbita:reconcile
        {--dry-run : Preview counter drift and purge candidates without writing}';

    protected $description = 'Reconcile denormalized counters and purge expired cache rows';

    public function handle(CounterReconciliationService $service): int
    {
        $this->info('Counter reconciliation');

        try {
            if ($this->option('dry-run')) {
                $this->comment('Mode: DRY RUN (no changes will be made)');

                $rows = [];
                foreach ($service->previewReconcileAll() as $name => $count) {
                    $rows[] = [$name, 'would touch', $count];
                }
                $rows[] = ['oembed_cache.expired', 'would purge', $service->countExpiredOembedCache()];
                $rows[] = ['cache.expired', 'would purge', $this->expiredCacheQuery()?->count() ?? 0];

                $this->table(['Counter', 'Action', 'Rows'], $rows);
                $this->comment('Run without --dry-run to apply changes.');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($service->reconcileAll() as $name => $count) {
                $rows[] = [$name, 'touched', $count];
            }
            $rows[] = ['oembed_cache.expired', 'purged', $service->purgeExpiredOembedCache()];
            $rows[] = ['cache.expired', 'purged', $this->expiredCacheQuery()?->delete() ?? 0];

            $this->table(['Counter', 'Action', 'Rows'], $rows);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Reconciliation failed: '.$e->getMessage());
            $this->line('The counter reconciliation relies on MySQL-specific SQL and cannot run on this database driver.');

            return self::FAILURE;
        }
    }

    private function expiredCacheQuery()
    {
        $store = (string) config('cache.default');

        if ((string) config("cache.stores.{$store}.driver") !== 'database') {
            return null;
        }

        $table = (string) config("cache.stores.{$store}.table", 'cache');

        if (! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->where('expiration', '<', Carbon::now()->getTimestamp());
    }
}
