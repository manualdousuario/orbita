<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(6), '.');

        return [
            'user_id' => User::factory(),
            'hashid' => HashId::unique('posts'),
            'title' => $title,
            'slug' => Slug::make($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'content' => fake()->paragraph(),
            'url' => null,
            'status' => 'published',
            'is_pinned' => false,
            'allow_comments' => true,
            'published_at' => now()->subHour(),
        ];
    }

    public function link(?string $url = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'content' => '',
            'url' => $url ?? fake()->url(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'closed',
            'allow_comments' => false,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'hidden']);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes): array => ['is_pinned' => true]);
    }
}
