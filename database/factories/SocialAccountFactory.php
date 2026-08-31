<?php

namespace Database\Factories;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SocialProvider::Google,
            'provider_user_id' => (string) Str::random(21),
            'provider_email' => fake()->unique()->safeEmail(),
            'provider_email_verified' => true,
            'provider_nickname' => null,
            'provider_name' => fake()->name(),
            'provider_avatar_url' => null,
            'last_login_at' => now(),
            'created_ip' => '127.0.0.1',
        ];
    }

    public function for_provider(SocialProvider $provider): static
    {
        return $this->state(fn (array $attributes) => ['provider' => $provider]);
    }
}
