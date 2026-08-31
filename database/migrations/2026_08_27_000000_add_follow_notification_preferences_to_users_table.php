<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "followed post" notification preference and turns every notification on by default.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = [
        'notify_replies_email',
        'notify_replies_system',
        'notify_mentions_email',
        'notify_mentions_system',
        'notify_follows_email',
        'notify_follows_system',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_follows_email')->default(true)->after('notify_mentions_system');
            $table->boolean('notify_follows_system')->default(true)->after('notify_follows_email');

            $table->boolean('notify_replies_email')->default(true)->change();
            $table->boolean('notify_replies_system')->default(true)->change();
            $table->boolean('notify_mentions_email')->default(true)->change();
            $table->boolean('notify_mentions_system')->default(true)->change();
        });

        DB::table('users')->update(array_fill_keys(self::COLUMNS, true));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_follows_email', 'notify_follows_system']);

            $table->boolean('notify_replies_email')->default(false)->change();
            $table->boolean('notify_replies_system')->default(false)->change();
            $table->boolean('notify_mentions_email')->default(false)->change();
            $table->boolean('notify_mentions_system')->default(false)->change();
        });
    }
};
