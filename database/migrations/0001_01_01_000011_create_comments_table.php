<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the comments table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('hashid', 10)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('nesting_level')->default(0);
            $table->enum('status', ['visible', 'hidden', 'removed', 'revision'])->default('visible');
            $table->integer('score')->default(0);
            $table->integer('reaction_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('nesting_level');
            $table->index('score');
            $table->index('created_at');

            // Plain replacement for the legacy DESC composite index (parent_id one added below).
            $table->index(['user_id', 'status', 'created_at']);
        });

        // Self-referencing FKs added after table creation.
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('version_of')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            // Composite index depends on parent_id, so it is added here.
            $table->index(['post_id', 'status', 'parent_id', 'created_at']);
        });

        // MySQL-only FULLTEXT guarded so migrations still run on SQLite.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('comments', function (Blueprint $table) {
                $table->fullText(['content']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
