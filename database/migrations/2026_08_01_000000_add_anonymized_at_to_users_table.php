<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('last_login_ip');

            $table->index('anonymized_at');
        });

        DB::table('users')->whereNotNull('deleted_at')->orderBy('id')->each(function ($user): void {
            DB::table('users')->where('id', $user->id)->update([
                'name' => null,
                'username' => 'anonymized-'.$user->id,
                'username_changed_at' => now(),
                'email' => 'anonymized-'.$user->id.'@orbita.invalid',
                'email_verified_at' => null,
                'password' => null,
                'remember_token' => null,
                'display_name' => 'Conta excluída',
                'bio' => null,
                'website' => null,
                'avatar_url' => null,
                'reputation_score' => 0,
                'role' => 'user',
                'notify_replies_email' => false,
                'notify_replies_system' => false,
                'notify_mentions_email' => false,
                'notify_mentions_system' => false,
                'default_comment_sort' => null,
                'last_login_at' => null,
                'last_login_ip' => null,
                'anonymized_at' => $user->deleted_at,
                'deleted_at' => null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['anonymized_at']);
            $table->dropColumn('anonymized_at');
        });
    }
};
