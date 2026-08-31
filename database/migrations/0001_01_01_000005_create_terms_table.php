<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->enum('taxonomy', ['category', 'tag']);
            $table->string('name', 100);
            $table->string('slug', 50);
            $table->text('description')->nullable();
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['taxonomy', 'slug'], 'unique_taxonomy_slug');
            $table->index('taxonomy');
            $table->index('slug');
            $table->index('name');
            $table->index('is_active');
            $table->index('usage_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
