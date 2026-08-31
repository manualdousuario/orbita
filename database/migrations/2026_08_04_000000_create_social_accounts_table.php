<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id', 191);
            $table->string('provider_email', 255)->nullable();
            $table->boolean('provider_email_verified')->default(false);
            $table->string('provider_nickname', 191)->nullable();
            $table->string('provider_name', 191)->nullable();
            $table->string('provider_avatar_url', 512)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('created_ip', 45)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
