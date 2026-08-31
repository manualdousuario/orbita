<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('file_hash', 64)->index();
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->string('file_extension', 10);
            $table->enum('file_type', ['image', 'video', 'audio', 'document', 'other'])->default('image');
            $table->string('media_type', 20)->default('post');
            $table->string('path', 500);
            $table->unsignedBigInteger('file_size');
            $table->json('metadata')->nullable();
            $table->boolean('is_nsfw')->default(false);
            $table->boolean('is_spoiler')->default(false)->index();
            $table->foreignId('nsfw_marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('nsfw_marked_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('upload_ip', 45)->nullable();
            $table->enum('status', ['active', 'processing', 'failed', 'deleted'])->default('active');
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('mime_type');
            $table->index('file_type');
            $table->index('media_type');
            $table->index('uploaded_by');
            $table->index('is_nsfw');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
