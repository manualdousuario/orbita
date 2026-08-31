<?php

declare(strict_types=1);

/**
 * posts.last_comment_at stays in sync with comments and the reconciliation job.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\CounterReconciliationService;
use App\Services\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function lastCommentPost(string $tag): Post
{
    return Post::create([
        'user_id' => User::factory()->createOne()->id,
        'hashid' => 'lca'.$tag,
        'title' => 'Post '.$tag,
        'slug' => 'post-'.$tag,
        'content' => 'corpo',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
}

/**
 * @param  array<string, mixed>  $attrs
 */
function backdatedComment(Post $post, string $when, array $attrs = []): Comment
{
    $comment = Comment::create([
        'user_id' => User::factory()->createOne()->id,
        'post_id' => $post->id,
        'hashid' => 'c'.substr(md5($when.$post->id.serialize($attrs)), 0, 9),
        'content' => 'comentário',
        'status' => 'visible',
    ] + $attrs);

    DB::table('comments')->where('id', $comment->id)->update(['created_at' => $when]);

    return $comment->fresh();
}

function lastCommentAt(Post $post): ?string
{
    return DB::table('posts')->where('id', $post->id)->value('last_comment_at');
}

it('a post with no comments has no last comment at', function () {
    expect(lastCommentAt(lastCommentPost('empty')))->toBeNull();
});

it('creating a comment sets it', function () {
    $post = lastCommentPost('create');
    backdatedComment($post, '2026-07-01 10:00:00');

    // The observer runs on create, before the timestamp is backdated, so re-derive.
    app(CounterReconciliationService::class)->reconcilePostLastCommentAt();

    expect((string) lastCommentAt($post))->toStartWith('2026-07-01 10:00:00');
});

it('tracks the newest comment not the latest written', function () {
    $post = lastCommentPost('newest');
    backdatedComment($post, '2026-07-10 10:00:00');
    backdatedComment($post, '2026-07-01 10:00:00');

    app(CounterReconciliationService::class)->reconcilePostLastCommentAt();

    expect((string) lastCommentAt($post))->toStartWith('2026-07-10 10:00:00');
});

it('deleting the newest comment falls back to the previous one', function () {
    $post = lastCommentPost('delete');
    $older = backdatedComment($post, '2026-07-01 10:00:00');
    $newest = backdatedComment($post, '2026-07-10 10:00:00');
    app(CounterReconciliationService::class)->reconcilePostLastCommentAt();

    $newest->delete();

    expect((string) lastCommentAt($post))->toStartWith('2026-07-01 10:00:00');
    expect($older->fresh())->not->toBeNull();
});

it('hiding the newest comment falls back too', function () {
    $post = lastCommentPost('hide');
    backdatedComment($post, '2026-07-01 10:00:00');
    $newest = backdatedComment($post, '2026-07-10 10:00:00');
    app(CounterReconciliationService::class)->reconcilePostLastCommentAt();

    $newest->update(['status' => 'hidden']);

    expect((string) lastCommentAt($post))->toStartWith('2026-07-01 10:00:00');
});

it('deleting the only comment clears it', function () {
    $post = lastCommentPost('clear');
    $only = backdatedComment($post, '2026-07-01 10:00:00');
    app(CounterReconciliationService::class)->reconcilePostLastCommentAt();

    $only->delete();

    expect(lastCommentAt($post))->toBeNull();
});

/** Revision snapshots are not comments; they must not move the feed. */
it('a revision snapshot is ignored', function () {
    $post = lastCommentPost('rev');
    $real = backdatedComment($post, '2026-07-01 10:00:00');
    backdatedComment($post, '2026-07-20 10:00:00', ['version_of' => $real->id]);

    app(CounterReconciliationService::class)->reconcilePostLastCommentAt();

    expect((string) lastCommentAt($post))->toStartWith('2026-07-01 10:00:00');
});

it('reconciliation repairs a drifted value', function () {
    $post = lastCommentPost('drift');
    backdatedComment($post, '2026-07-05 10:00:00');

    // Simulate a missed observer event (bulk operation, direct DB edit).
    DB::table('posts')->where('id', $post->id)->update(['last_comment_at' => null]);
    expect(app(CounterReconciliationService::class)->previewPostLastCommentAt())->toBe(1);

    app(CounterReconciliationService::class)->reconcilePostLastCommentAt();

    expect((string) lastCommentAt($post))->toStartWith('2026-07-05 10:00:00');
    expect(app(CounterReconciliationService::class)->previewPostLastCommentAt())->toBe(0);
});

it('the recent activity feed orders by the denormalised column', function () {
    $older = lastCommentPost('feedold');
    $newer = lastCommentPost('feednew');
    backdatedComment($older, '2026-07-01 10:00:00');
    backdatedComment($newer, '2026-07-20 10:00:00');
    app(CounterReconciliationService::class)->reconcilePostLastCommentAt();

    Cache::flush();
    $rows = app(RankingService::class)->getPostsByRecentComments(10, 0);

    $ids = array_map(static fn ($r) => (int) $r->id, $rows);
    expect($ids)->toBe([$newer->id, $older->id]);

    // The column still reaches the view: it used to arrive as a subquery alias.
    expect($rows[0]->last_comment_at)->not->toBeNull();
});
