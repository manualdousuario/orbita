<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wordpress_import_map');
    }

    public function down(): void
    {
        Schema::create('wordpress_import_map', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['post', 'comment', 'user']);
            $table->unsignedBigInteger('wp_id');
            $table->unsignedBigInteger('local_id');
            $table->dateTime('wp_modified_at')->nullable();
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->unique(['entity_type', 'wp_id']);
            $table->index(['entity_type', 'local_id']);
        });
    }
};
