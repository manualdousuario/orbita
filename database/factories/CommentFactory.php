<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'parent_id' => null,
            'hashid' => HashId::unique('comments'),
            'content' => fake()->paragraph(),
            'status' => 'visible',
            'nesting_level' => 0,
        ];
    }

    public function replyTo(Comment $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'post_id' => $parent->post_id,
            'parent_id' => $parent->id,
            'nesting_level' => (int) $parent->nesting_level + 1,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'hidden']);
    }

    public function removed(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'removed']);
    }
}
