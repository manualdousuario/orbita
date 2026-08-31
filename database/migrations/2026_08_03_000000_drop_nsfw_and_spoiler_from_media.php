<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['is_nsfw']);
            $table->dropIndex(['is_spoiler']);
            $table->dropConstrainedForeignId('nsfw_marked_by');
            $table->dropColumn(['is_nsfw', 'is_spoiler', 'nsfw_marked_at']);
        });

        DB::table('settings')->where('key', 'orbita.images.allow_spoiler_flag')->delete();

        Settings::flush();
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->boolean('is_nsfw')->default(false)->after('metadata');
            $table->boolean('is_spoiler')->default(false)->after('is_nsfw');
            $table->foreignId('nsfw_marked_by')->nullable()->after('is_spoiler')->constrained('users')->nullOnDelete();
            $table->timestamp('nsfw_marked_at')->nullable()->after('nsfw_marked_by');

            $table->index('is_nsfw');
            $table->index('is_spoiler');
        });
    }
};
