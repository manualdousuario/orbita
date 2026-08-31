<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE user_tokens MODIFY COLUMN token_type ENUM('reset_password', 'set_password', 'email_verification', 'email_change', 'account_deletion') NOT NULL DEFAULT 'reset_password'");
    }

    public function down(): void
    {
        DB::table('user_tokens')->where('token_type', 'account_deletion')->delete();

        DB::statement("ALTER TABLE user_tokens MODIFY COLUMN token_type ENUM('reset_password', 'set_password', 'email_verification', 'email_change') NOT NULL DEFAULT 'reset_password'");
    }
};
