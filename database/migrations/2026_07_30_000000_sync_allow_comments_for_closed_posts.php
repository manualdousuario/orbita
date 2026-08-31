<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill for closeInactivePosts(): auto-closed posts must also set allow_comments = false.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')
            ->where('status', 'closed')
            ->where('allow_comments', true)
            ->update(['allow_comments' => false]);
    }

    public function down(): void
    {
        // No-op: cannot distinguish rows locked independently before this migration.
    }
};
