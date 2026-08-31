<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rate_limits');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reputation_score');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('reputation_score')->default(0)->after('avatar_url');
        });

        Schema::create('rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('reset_at')->nullable()->index();
            $table->timestamps();
        });
    }
};
