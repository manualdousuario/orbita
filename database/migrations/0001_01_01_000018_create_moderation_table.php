<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['hide', 'unhide', 'remove', 'ban_user', 'unban_user', 'pin', 'unpin', 'restore']);
            $table->enum('target_type', ['post', 'comment', 'user']);
            $table->unsignedBigInteger('target_id');
            $table->text('reason');
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
            $table->index('expires_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation');
    }
};
