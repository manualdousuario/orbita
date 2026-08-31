<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds lock_comments/unlock_comments to the moderation.action enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE moderation MODIFY COLUMN action ENUM('hide', 'unhide', 'remove', 'ban_user', 'unban_user', 'pin', 'unpin', 'restore', 'lock_comments', 'unlock_comments') NOT NULL");
    }

    public function down(): void
    {
        DB::table('moderation')->whereIn('action', ['lock_comments', 'unlock_comments'])->delete();

        DB::statement("ALTER TABLE moderation MODIFY COLUMN action ENUM('hide', 'unhide', 'remove', 'ban_user', 'unban_user', 'pin', 'unpin', 'restore') NOT NULL");
    }
};
