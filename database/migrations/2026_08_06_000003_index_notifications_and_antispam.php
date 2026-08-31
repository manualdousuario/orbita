<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'notifications_user_id_created_at_index');
            $table->index(['is_read', 'read_at'], 'notifications_is_read_read_at_index');
            $table->index(['is_read', 'created_at'], 'notifications_is_read_created_at_index');
            $table->dropIndex('notifications_user_id_index');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index(['user_id', 'version_of', 'created_at'], 'posts_user_version_created_index');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['user_id', 'version_of', 'created_at'], 'comments_user_version_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('user_id', 'notifications_user_id_index');
            $table->dropIndex('notifications_user_id_created_at_index');
            $table->dropIndex('notifications_is_read_read_at_index');
            $table->dropIndex('notifications_is_read_created_at_index');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_user_version_created_index');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_user_version_created_index');
        });
    }
};
