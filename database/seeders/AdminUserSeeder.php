<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Seeds the default admin user ('orbita'). Idempotent. Password should be changed after first login. */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrNew(['email' => 'orbita@orbita.social.br'])
            ->forceFill([
                'username' => 'orbita',
                'email_verified_at' => now(),
                'password' => 'orbita', // cast 'hashed' on the model hashes this
                'display_name' => 'Órbita',
                'role' => 'admin',
                'is_banned' => false,
            ])
            ->save();
    }
}
