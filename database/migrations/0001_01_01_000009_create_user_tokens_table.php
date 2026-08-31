<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->enum('token_type', ['reset_password', 'set_password', 'email_verification', 'email_change'])->default('reset_password');
            $table->string('email', 255);
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->string('request_ip', 45);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('email');
            $table->index('token_type');
            $table->index('expires_at');
            $table->index('is_used');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tokens');
    }
};
