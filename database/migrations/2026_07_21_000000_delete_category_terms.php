<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the `category` taxonomy; the app keeps only tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('terms')->where('taxonomy', 'category')->delete();
    }

    public function down(): void
    {
        // No-op: deleted category terms cannot be restored.
    }
};
