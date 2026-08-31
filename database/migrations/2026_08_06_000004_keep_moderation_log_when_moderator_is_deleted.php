<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE moderation DROP FOREIGN KEY moderation_moderator_id_foreign');
        DB::statement(
            'ALTER TABLE moderation
             ADD CONSTRAINT moderation_moderator_id_foreign
             FOREIGN KEY (moderator_id) REFERENCES users (id) ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        $orphans = DB::table('moderation')->whereNull('moderator_id')->count();

        if ($orphans > 0) {
            throw new RuntimeException(
                "Cannot restore ON DELETE CASCADE: {$orphans} moderation row(s) already have a null moderator_id."
            );
        }

        DB::statement('ALTER TABLE moderation DROP FOREIGN KEY moderation_moderator_id_foreign');
        DB::statement(
            'ALTER TABLE moderation
             ADD CONSTRAINT moderation_moderator_id_foreign
             FOREIGN KEY (moderator_id) REFERENCES users (id) ON DELETE CASCADE'
        );
    }
};
