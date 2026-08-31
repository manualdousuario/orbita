<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['posts', 'comments'] as $table) {
            $this->assertNoDuplicates($table);

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unique('hashid', "{$table}_hashid_unique");
            });

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex("{$table}_hashid_index");
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'comments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->index('hashid', "{$table}_hashid_index");
                $blueprint->dropUnique("{$table}_hashid_unique");
            });
        }
    }

    private function assertNoDuplicates(string $table): void
    {
        $duplicates = DB::table($table)
            ->select('hashid')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('hashid')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $sample = $duplicates
            ->map(fn (object $row): string => "{$row->hashid} (x{$row->total})")
            ->implode(', ');

        throw new RuntimeException(
            "Cannot make {$table}.hashid unique: duplicates present. Resolve these first — {$sample}"
        );
    }
};
