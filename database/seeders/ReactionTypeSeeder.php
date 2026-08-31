<?php

namespace Database\Seeders;

use App\Models\ReactionType;
use Illuminate\Database\Seeder;

/** Seeds the default reaction types (like, love, laugh, etc.). Idempotent. */
class ReactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'like', 'name' => 'Curtir', 'emoji' => '👍', 'score' => 1, 'allowed_roles' => ['user', 'moderator', 'admin'], 'display_order' => 1],
            ['slug' => 'love', 'name' => 'Amei', 'emoji' => '❤️', 'score' => 2, 'allowed_roles' => ['user', 'moderator', 'admin'], 'display_order' => 2],
            ['slug' => 'laugh', 'name' => 'Haha', 'emoji' => '😂', 'score' => 1, 'allowed_roles' => ['user', 'moderator', 'admin'], 'display_order' => 3],
            ['slug' => 'insightful', 'name' => 'Esclarecedor', 'emoji' => '💡', 'score' => 3, 'allowed_roles' => ['user', 'moderator', 'admin'], 'display_order' => 4],
            ['slug' => 'dislike', 'name' => 'Não curti', 'emoji' => '👎', 'score' => -1, 'allowed_roles' => ['user', 'moderator', 'admin'], 'display_order' => 5],
        ];

        foreach ($types as $type) {
            ReactionType::updateOrCreate(
                ['slug' => $type['slug']],
                $type + ['is_active' => true],
            );
        }
    }
}
