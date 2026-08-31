<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the bookmarks (user saves) table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Not polymorphic (unlike user_reactions): saves apply only to posts, keeping "Salvos" a single join with a real FK.
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One save per (user, post): the toggle relies on this to stay idempotent.
            $table->unique(['user_id', 'post_id'], 'unique_user_bookmark');

            // The "Salvos" page reads a user's saves, most recent first.
            $table->index(['user_id', 'created_at']);

            // The feed's batch lookup filters by post ids for one user.
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
