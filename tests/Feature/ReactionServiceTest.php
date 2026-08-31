<?php

declare(strict_types=1);

/**
 * Reaction toggle, summary, and denormalised counters.
 */

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Models\UserReaction;
use App\Services\ReactionService;
use App\Support\HashId;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function reactablePost(User $user): Post
{
    return Post::create([
        'user_id' => $user->id,
        'hashid' => HashId::encode(1),
        'title' => 'Post reagível',
        'slug' => 'post-reagivel',
        'content' => 'Conteúdo',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

/**
 * An explicit throw rather than an assertion: it narrows the nullable type for
 * the analyser, which expect() does not.
 *
 * @return array<string, mixed>
 */
function toggleReaction(ReactionService $service, User $user, Post $post, string $slug): array
{
    $type = $service->usableType($slug, $user->role?->value ?? 'user');

    if (! $type instanceof ReactionType) {
        throw new RuntimeException("reaction type {$slug} should be usable");
    }

    return $service->toggle((int) $user->id, 'post', (int) $post->id, $type, $post->fresh());
}

it('toggle add sets count and user reaction', function () {
    seed(ReactionTypeSeeder::class);
    $service = app(ReactionService::class);
    $user = User::factory()->createOne(['username' => 'u1', 'email_verified_at' => now()]);
    $post = reactablePost($user);

    $result = toggleReaction($service, $user, $post, 'like');

    expect($result['action'])->toBe('added')
        ->and($result['user_reaction'])->toBe('like')
        ->and($result['count'])->toBe(1)
        ->and($result['reactions'])->toBe(['like' => 1]);

    // Denormalised reaction_count on the target is kept in sync.
    expect((int) $post->fresh()?->reaction_count)->toBe(1);

    assertDatabaseHas('user_reactions', [
        'user_id' => $user->id,
        'reactable_type' => 'post',
        'reactable_id' => $post->id,
    ]);
});

it('toggle same slug again removes the reaction', function () {
    seed(ReactionTypeSeeder::class);
    $service = app(ReactionService::class);
    $user = User::factory()->createOne(['username' => 'u2', 'email_verified_at' => now()]);
    $post = reactablePost($user);

    toggleReaction($service, $user, $post, 'like');
    $result = toggleReaction($service, $user, $post, 'like');

    expect($result['action'])->toBe('removed')
        ->and($result['user_reaction'])->toBeNull()
        ->and($result['count'])->toBe(0)
        ->and($result['reactions'])->toBe([])
        ->and((int) $post->fresh()?->reaction_count)->toBe(0);

    assertDatabaseMissing('user_reactions', [
        'user_id' => $user->id,
        'reactable_type' => 'post',
        'reactable_id' => $post->id,
    ]);
});

it('toggle a different slug updates the reaction', function () {
    seed(ReactionTypeSeeder::class);
    $service = app(ReactionService::class);
    $user = User::factory()->createOne(['username' => 'u3', 'email_verified_at' => now()]);
    $post = reactablePost($user);

    toggleReaction($service, $user, $post, 'love');
    $result = toggleReaction($service, $user, $post, 'like');

    expect($result['action'])->toBe('updated')
        ->and($result['user_reaction'])->toBe('like')
        ->and($result['count'])->toBe(1)
        ->and($result['reactions'])->toBe(['like' => 1])
        ->and((int) $post->fresh()?->reaction_count)->toBe(1);

    // Exactly one row remains for this user/target, now pointing at 'like'.
    $likeId = ReactionType::where('slug', 'like')->value('id');
    assertDatabaseHas('user_reactions', [
        'user_id' => $user->id,
        'reactable_type' => 'post',
        'reactable_id' => $post->id,
        'reaction_type_id' => $likeId,
    ]);

    expect(UserReaction::where('reactable_type', 'post')->where('reactable_id', $post->id)->count())->toBe(1);
});

it('summary for user reports score and user reaction', function () {
    seed(ReactionTypeSeeder::class);
    $service = app(ReactionService::class);
    $user = User::factory()->createOne(['username' => 'u4', 'email_verified_at' => now()]);
    $post = reactablePost($user);

    toggleReaction($service, $user, $post, 'insightful'); // score 3

    $summary = $service->summaryForUser('post', (int) $post->id, (int) $user->id);
    expect($summary['score'])->toBe(3)
        ->and($summary['user_reaction'])->toBe('insightful');

    // Anonymous view carries counts but no user_reaction.
    $anon = $service->summaryForUser('post', (int) $post->id, null);
    expect($anon['user_reaction'])->toBeNull()
        ->and($anon['count'])->toBe(1);
});
