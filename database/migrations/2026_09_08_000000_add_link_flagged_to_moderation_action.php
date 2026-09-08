<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE moderation MODIFY COLUMN action ENUM('hide', 'unhide', 'remove', 'ban_user', 'unban_user', 'pin', 'unpin', 'restore', 'lock_comments', 'unlock_comments', 'auto_hide', 'referral_stripped', 'activate_user', 'link_flagged') NOT NULL");
    }

    public function down(): void
    {
        DB::table('moderation')->where('action', 'link_flagged')->delete();

        DB::statement("ALTER TABLE moderation MODIFY COLUMN action ENUM('hide', 'unhide', 'remove', 'ban_user', 'unban_user', 'pin', 'unpin', 'restore', 'lock_comments', 'unlock_comments', 'auto_hide', 'referral_stripped', 'activate_user') NOT NULL");
    }
};
