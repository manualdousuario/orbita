<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deletes the obsolete orbita.rybbit.* settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->whereIn('key', ['orbita.rybbit.site_id', 'orbita.rybbit.script_url'])
            ->delete();

        Settings::flush();
    }

    public function down(): void
    {
        //
    }
};
