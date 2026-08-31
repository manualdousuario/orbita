<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reaction_type_id')->constrained('reaction_types')->cascadeOnDelete();
            $table->enum('reactable_type', ['post', 'comment']);
            $table->unsignedBigInteger('reactable_id');
            $table->timestamps();

            $table->unique(['user_id', 'reactable_type', 'reactable_id'], 'unique_user_reaction');
            $table->index('user_id');
            $table->index(['reactable_type', 'reactable_id']);
            $table->index('reaction_type_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reactions');
    }
};
