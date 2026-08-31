<?php

declare(strict_types=1);

/**
 * Post scoring formula and ranking feed filters.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Models\UserReaction;
use App\Services\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rankingUser(string $username): User
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
function rankingPost(User $author, string $hashid, array $overrides = []): Post
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

it('score is reactions plus comments minus time decay', function () {
    config(['orbita.posts.score_decay_hours' => 3]);

    $author = rankingUser('author');
    $other = rankingUser('other');

    // Published 6h ago => temporal penalty = floor(6/3) = 2
    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'p1',
        'title' => 'T',
        'slug' => 't',
        'content' => 'c',
        'status' => 'published',
        'published_at' => now()->subHours(6),
    ]);

    $like = ReactionType::create(['slug' => 'like', 'name' => 'Curtir', 'emoji' => '👍', 'score' => 1, 'allowed_roles' => ['user'], 'display_order' => 1]);
    $insightful = ReactionType::create(['slug' => 'insightful', 'name' => 'Esclarecedor', 'emoji' => '💡', 'score' => 3, 'allowed_roles' => ['user'], 'display_order' => 2]);

    // reactionScore = 1 + 3 = 4
    UserReaction::create(['user_id' => $other->id, 'reaction_type_id' => $like->id, 'reactable_type' => 'post', 'reactable_id' => $post->id]);
    UserReaction::create(['user_id' => $author->id, 'reaction_type_id' => $insightful->id, 'reactable_type' => 'post', 'reactable_id' => $post->id]);

    // commentScore = 1 (comment by $other; author's own comment does not count)
    Comment::create(['user_id' => $other->id, 'post_id' => $post->id, 'hashid' => 'c1', 'content' => 'x', 'status' => 'visible', 'nesting_level' => 0]);
    Comment::create(['user_id' => $author->id, 'post_id' => $post->id, 'hashid' => 'c2', 'content' => 'y', 'status' => 'visible', 'nesting_level' => 0]);

    // score = max(0, 4 + 1 - 2) = 3
    expect(app(RankingService::class)->calculatePostScore($post->id))->toBe(3);
});

it('score floors at zero when penalty dominates', function () {
    config(['orbita.posts.score_decay_hours' => 1]);

    $author = rankingUser('a2');
    $post = Post::create([
        'user_id' => $author->id, 'hashid' => 'p2', 'title' => 'T', 'slug' => 't2',
        'content' => 'c', 'status' => 'published', 'published_at' => now()->subHours(50),
    ]);

    // No reactions, no comments, huge penalty => clamps to 0
    expect(app(RankingService::class)->calculatePostScore($post->id))->toBe(0);
});

it('returns zero for missing post', function () {
    expect(app(RankingService::class)->calculatePostScore(999999))->toBe(0);
});

it('reactions feed excludes posts with comments closed', function () {
    $author = rankingUser('r1');

    $open = rankingPost($author, 'ro', ['reaction_count' => 10, 'allow_comments' => true]);
    rankingPost($author, 'rc', ['reaction_count' => 999, 'allow_comments' => false]);

    $ids = array_column(app(RankingService::class)->getPostsByReactionsOnly(), 'id');

    expect($ids)->toContain($open->id)->toHaveCount(1);
});

it('comment count feed excludes posts with comments closed', function () {
    $author = rankingUser('c1');

    $open = rankingPost($author, 'co', ['comment_count' => 5, 'allow_comments' => true]);
    rankingPost($author, 'cc', ['comment_count' => 999, 'allow_comments' => false]);

    $ids = array_column(app(RankingService::class)->getPostsByCommentCount(), 'id');

    expect($ids)->toContain($open->id)->toHaveCount(1);
});

it('no comments feed excludes posts with comments closed', function () {
    $author = rankingUser('n1');

    $open = rankingPost($author, 'no', ['comment_count' => 0, 'allow_comments' => true]);
    rankingPost($author, 'nc', ['comment_count' => 0, 'allow_comments' => false]);

    $ids = array_column(app(RankingService::class)->getPostsWithoutComments(), 'id');

    expect($ids)->toContain($open->id)->toHaveCount(1);
});

it('no comments feed orders newest first', function () {
    $author = rankingUser('n2');

    $older = rankingPost($author, 'no-old', ['published_at' => now()->subDays(5)]);
    $newer = rankingPost($author, 'no-new', ['published_at' => now()->subDay()]);

    $ids = array_column(app(RankingService::class)->getPostsWithoutComments(), 'id');

    expect($ids)->toBe([$newer->id, $older->id]);
});

it('paginate posts total respects allow comments filter', function () {
    $author = rankingUser('p1');

    rankingPost($author, 'pt-open', ['comment_count' => 5, 'allow_comments' => true]);
    rankingPost($author, 'pt-closed', ['comment_count' => 5, 'allow_comments' => false]);

    $paginator = app(RankingService::class)->paginatePosts('getPostsByCommentCount', 25, 1);

    expect($paginator->total())->toBe(1);
});
