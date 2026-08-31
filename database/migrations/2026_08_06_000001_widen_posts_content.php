<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE posts MODIFY COLUMN content MEDIUMTEXT NOT NULL');
    }

    public function down(): void
    {
        $oversized = DB::table('posts')->whereRaw('OCTET_LENGTH(content) > 65535')->count();

        if ($oversized > 0) {
            throw new RuntimeException(
                "Cannot narrow posts.content back to TEXT: {$oversized} row(s) already exceed 65535 bytes."
            );
        }

        DB::statement('ALTER TABLE posts MODIFY COLUMN content TEXT NOT NULL');
    }
};
