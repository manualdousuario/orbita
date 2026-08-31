<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the posts table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('hashid', 10)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 300);
            $table->string('url', 2048)->nullable();
            $table->string('slug', 355)->index();
            $table->text('content');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->boolean('notify_replies_email')->nullable();
            $table->boolean('notify_replies_system')->nullable();
            $table->boolean('notify_mentions_email')->nullable();
            $table->boolean('notify_mentions_system')->nullable();
            $table->enum('status', ['draft', 'published', 'hidden', 'revision', 'closed'])->default('published');
            $table->integer('score')->default(0);
            $table->integer('comment_count')->default(0);
            $table->integer('reaction_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_pinned');
            $table->index('score');
            $table->index('published_at');
            $table->index('created_at');

            // Plain replacements for the legacy DESC composite indexes.
            $table->index(['status', 'published_at']);
            $table->index(['status', 'score', 'published_at']);
            $table->index(['user_id', 'status', 'created_at']);
        });

        // Self-referencing FK added after table creation.
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('version_of')->nullable()->constrained('posts')->cascadeOnDelete();
        });

        // MySQL-only features guarded so migrations still run on SQLite.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('posts', function (Blueprint $table) {
                $table->fullText(['title', 'content']);
            });
            DB::statement('CREATE INDEX idx_posts_url_prefix ON posts (url(191))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
