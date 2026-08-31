<?php

declare(strict_types=1);

/**
 * Generation-scoped invalidation of the ranking caches: what drops them, and what is
 * deliberately left to expire.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Services\RankingService;
use App\Services\ReactionService;
use App\Support\FeedGeneration;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function invalidationUser(string $username): User
{
    return User::create([
        'username' => $username,
        'email' => $username.'@example.com',
        'password' => 'secret123',
        'display_name' => $username,
        'role' => 'user',
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function invalidationPost(User $author, string $hashid, array $overrides = []): Post
{
    return Post::create(array_merge([
        'user_id' => $author->id,
        'hashid' => $hashid,
        'title' => 'T '.$hashid,
        'slug' => 't-'.$hashid,
        'content' => 'c',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'allow_comments' => true,
        'comment_count' => 0,
        'reaction_count' => 0,
    ], $overrides));
}

/** Long enough that anything still cached can only be explained by a missing bump. */
beforeEach(function () {
    config(['orbita.cache.ranking_ttl' => ['default' => 86400]]);
});

it('a new post shows up immediately even though the feed was cached under a long ttl', function () {
    $author = invalidationUser('autora');
    invalidationPost($author, 'p1');

    $before = array_column(app(RankingService::class)->getAllPosts(25, 0), 'hashid');
    expect($before)->toBe(['p1']);

    invalidationPost($author, 'p2', ['published_at' => now()]);

    $after = array_column(app(RankingService::class)->getAllPosts(25, 0), 'hashid');
    expect($after)->toBe(['p2', 'p1']);
});

it('the cached total is dropped along with the rows', function () {
    $author = invalidationUser('autora');
    invalidationPost($author, 'p1');

    expect(app(RankingService::class)->feedTotal('getAllPosts'))->toBe(1);

    invalidationPost($author, 'p2');

    expect(app(RankingService::class)->feedTotal('getAllPosts'))->toBe(2);
});

it('a new comment drops the cached comment feed', function () {
    $author = invalidationUser('autora');
    $reader = invalidationUser('leitor');
    $post = invalidationPost($author, 'p1');

    expect(app(RankingService::class)->getAllComments(25, 0))->toBe([]);

    Comment::create([
        'post_id' => $post->id,
        'user_id' => $reader->id,
        'hashid' => 'c1',
        'content' => 'primeiro',
        'status' => 'visible',
    ]);

    expect(app(RankingService::class)->getAllComments(25, 0))->toHaveCount(1);
});

it('hiding a comment drops the cached comment feed', function () {
    $author = invalidationUser('autora');
    $reader = invalidationUser('leitor');
    $post = invalidationPost($author, 'p1');

    $comment = Comment::create([
        'post_id' => $post->id,
        'user_id' => $reader->id,
        'hashid' => 'c1',
        'content' => 'primeiro',
        'status' => 'visible',
    ]);

    expect(app(RankingService::class)->getAllComments(25, 0))->toHaveCount(1);

    $comment->update(['status' => 'hidden']);

    expect(app(RankingService::class)->getAllComments(25, 0))->toBe([]);
});

it('editing a post drops the cached rows, because the feed selects the whole row', function () {
    $author = invalidationUser('autora');
    $post = invalidationPost($author, 'p1');

    expect(array_column(app(RankingService::class)->getAllPosts(25, 0), 'title'))->toBe(['T p1']);

    $post->update(['title' => 'Título corrigido']);

    expect(array_column(app(RankingService::class)->getAllPosts(25, 0), 'title'))->toBe(['Título corrigido']);
});

it('a revision snapshot does not invalidate anything', function () {
    $author = invalidationUser('autora');
    $post = invalidationPost($author, 'p1');

    $generation = app(FeedGeneration::class)->current();

    invalidationPost($author, 'p1-v1', ['version_of' => $post->id]);

    expect(app(FeedGeneration::class)->current())->toBe($generation);
});

/**
 * Deliberate: reactions are the highest-frequency write in the app and only reorder rows,
 * never add or remove them. Bumping on each one would invalidate the feeds faster than
 * they could be reused, so the reaction-ordered tabs carry a short TTL instead.
 */
it('a reaction does not invalidate the feeds', function () {
    seed(ReactionTypeSeeder::class);

    $author = invalidationUser('autora');
    $voter = invalidationUser('votante');
    $post = invalidationPost($author, 'p1');

    $generation = app(FeedGeneration::class)->current();

    $type = ReactionType::query()->where('slug', 'like')->firstOrFail();
    app(ReactionService::class)->toggle($voter->id, 'post', (int) $post->id, $type, $post);

    expect(app(FeedGeneration::class)->current())->toBe($generation);
});

/**
 * Guards the one direction that would actually corrupt a read: a counter that restarts
 * from a low value could land back on a generation whose keys are still cached. Redis
 * INCR on a missing key returns 1, so bump() has to seed from the clock instead.
 */
it('a counter that was lost reseeds from the clock instead of restarting at one', function () {
    Cache::forget('ranking:generation');
    app()->forgetScopedInstances();

    app(FeedGeneration::class)->bump();

    expect(app(FeedGeneration::class)->current())->toBeGreaterThan(1_000_000);
});
