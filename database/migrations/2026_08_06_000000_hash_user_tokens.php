<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE user_tokens SET token = SHA2(token, 256) WHERE token REGEXP '[^0-9a-f]'");
    }

    public function down(): void
    {
        DB::table('user_tokens')->where('is_used', false)->delete();
    }
};
