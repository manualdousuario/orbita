<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title', 200);
            $table->longText('content');
            $table->boolean('show_in_footer')->default(false);
            $table->boolean('is_guidelines')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('show_in_footer');
            $table->index('is_guidelines');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
