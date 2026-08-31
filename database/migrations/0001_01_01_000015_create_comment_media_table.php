<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->integer('display_order')->default(0);
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'media_id'], 'unique_comment_media');
            $table->index('comment_id');
            $table->index('media_id');
            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_media');
    }
};
