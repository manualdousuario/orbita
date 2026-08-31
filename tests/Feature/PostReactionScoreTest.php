<?php

declare(strict_types=1);

/**
 * posts.reaction_score stays in sync with reactions and the reconciliation job.
 */

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Services\CounterReconciliationService;
use App\Services\RankingService;
use App\Services\ReactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function reactionScorePost(string $tag): Post
{
    return Post::create([
        'user_id' => User::factory()->createOne()->id,
        'hashid' => 'rs'.$tag,
        'title' => 'Post '.$tag,
        'slug' => 'post-'.$tag,
        'content' => 'corpo',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'allow_comments' => true,
    ]);
}

function weightedReactionType(string $slug, int $score): ReactionType
{
    return ReactionType::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'emoji' => '🙂',
        'score' => $score,
        'display_order' => 1,
        'allowed_roles' => ['user', 'moderator', 'admin'],
        'is_active' => true,
    ]);
}

function reactionScoreOf(Post $post): int
{
    return (int) DB::table('posts')->where('id', $post->id)->value('reaction_score');
}

it('a post with no reactions scores zero', function () {
    expect(reactionScoreOf(reactionScorePost('none')))->toBe(0);
});

it('toggling a reaction writes the weighted sum', function () {
    $post = reactionScorePost('sum');
    $heavy = weightedReactionType('foguete', 5);
    $service = app(ReactionService::class);

    $service->toggle(User::factory()->createOne()->id, 'post', $post->id, $heavy, $post);
    expect(reactionScoreOf($post))->toBe(5);

    $service->toggle(User::factory()->createOne()->id, 'post', $post->id, $heavy, $post->fresh());
    expect(reactionScoreOf($post))->toBe(10);
});

/** The score is weighted, not a headcount -- that is what reaction_count is for. */
it('the score is not the reaction count', function () {
    $post = reactionScorePost('weight');
    $heavy = weightedReactionType('foguete', 5);
    $light = weightedReactionType('curtir', 1);
    $service = app(ReactionService::class);

    $service->toggle(User::factory()->createOne()->id, 'post', $post->id, $heavy, $post);
    $service->toggle(User::factory()->createOne()->id, 'post', $post->id, $light, $post->fresh());

    $row = DB::table('posts')->where('id', $post->id)->first();
    expect((int) $row->reaction_score)->toBe(6)
        ->and((int) $row->reaction_count)->toBe(2);
});

it('removing a reaction lowers the score', function () {
    $post = reactionScorePost('remove');
    $type = weightedReactionType('foguete', 5);
    $user = User::factory()->createOne()->id;
    $service = app(ReactionService::class);

    $service->toggle($user, 'post', $post->id, $type, $post);
    expect(reactionScoreOf($post))->toBe(5);

    // Toggling the same slug again removes it.
    $service->toggle($user, 'post', $post->id, $type, $post->fresh());
    expect(reactionScoreOf($post))->toBe(0);
});

it('switching reaction type reweights the score', function () {
    $post = reactionScorePost('switch');
    $heavy = weightedReactionType('foguete', 5);
    $light = weightedReactionType('curtir', 1);
    $user = User::factory()->createOne()->id;
    $service = app(ReactionService::class);

    $service->toggle($user, 'post', $post->id, $heavy, $post);
    $service->toggle($user, 'post', $post->id, $light, $post->fresh());

    expect(reactionScoreOf($post))->toBe(1)
        ->and((int) DB::table('posts')->where('id', $post->id)->value('reaction_count'))->toBe(1);
});

/**
 * Reweighting a reaction type changes every post that used it, via the scheduled pass.
 */
it('reconciliation catches a reweighted reaction type', function () {
    $post = reactionScorePost('reweight');
    $type = weightedReactionType('foguete', 5);
    app(ReactionService::class)->toggle(User::factory()->createOne()->id, 'post', $post->id, $type, $post);
    expect(reactionScoreOf($post))->toBe(5);

    $type->update(['score' => 50]);
    expect(reactionScoreOf($post))->toBe(5, 'nothing recomputes it until reconcile runs')
        ->and(app(CounterReconciliationService::class)->previewPostReactionScore())->toBe(1);

    app(CounterReconciliationService::class)->reconcilePostReactionScore();

    expect(reactionScoreOf($post))->toBe(50)
        ->and(app(CounterReconciliationService::class)->previewPostReactionScore())->toBe(0);
});

it('reconciliation repairs a drifted value', function () {
    $post = reactionScorePost('drift');
    $type = weightedReactionType('foguete', 5);
    app(ReactionService::class)->toggle(User::factory()->createOne()->id, 'post', $post->id, $type, $post);

    DB::table('posts')->where('id', $post->id)->update(['reaction_score' => 999]);

    app(CounterReconciliationService::class)->reconcilePostReactionScore();

    expect(reactionScoreOf($post))->toBe(5);
});

it('the feed orders by the denormalised column', function () {
    $low = reactionScorePost('feedlow');
    $high = reactionScorePost('feedhigh');
    $heavy = weightedReactionType('foguete', 5);
    $light = weightedReactionType('curtir', 1);
    $service = app(ReactionService::class);

    $service->toggle(User::factory()->createOne()->id, 'post', $low->id, $light, $low);
    $service->toggle(User::factory()->createOne()->id, 'post', $high->id, $heavy, $high);

    Cache::flush();
    $rows = app(RankingService::class)->getPostsByReactionsOnly(10, 0);

    $ids = array_map(static fn ($r) => (int) $r->id, $rows);
    expect($ids)->toBe([$high->id, $low->id])
        ->and((int) $rows[0]->reaction_score)->toBe(5);
});

/** posts.score carries comments and time decay; it must not become the reaction sum. */
it('the ranking score stays distinct from the reaction score', function () {
    $post = reactionScorePost('distinct');
    $type = weightedReactionType('foguete', 5);
    app(ReactionService::class)->toggle(User::factory()->createOne()->id, 'post', $post->id, $type, $post);

    $row = DB::table('posts')->where('id', $post->id)->first();
    expect((int) $row->reaction_score)->toBe(5)
        ->and((int) $row->score)->not->toBe((int) $row->reaction_score);
});
