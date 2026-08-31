<?php

declare(strict_types=1);

/**
 * Comment permalinks must land on the page that contains the comment.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Monotonic across the file: it drives both the hashid and the created_at
 * ordering the pagination reads.
 */
function deepLinkSeq(): int
{
    static $n = 0;

    return ++$n;
}

function deepLinkPost(): Post
{
    $author = User::factory()->createOne(['username' => 'autor_deep', 'email_verified_at' => now()]);

    return Post::create([
        'user_id' => $author->id, 'hashid' => 'deep1', 'title' => 'Assunto', 'slug' => 'assunto',
        'content' => 'corpo', 'status' => 'published', 'allow_comments' => true, 'published_at' => now(),
    ]);
}

function deepLinkComment(Post $post, string $text, ?Comment $parent = null): Comment
{
    $seq = deepLinkSeq();

    return Comment::create([
        'user_id' => $post->user_id,
        'post_id' => $post->id,
        'parent_id' => $parent?->id,
        'hashid' => 'dl'.$seq,
        'content' => $text,
        'status' => 'visible',
        'nesting_level' => $parent ? $parent->nesting_level + 1 : 0,
        'created_at' => now()->addSeconds($seq),
    ]);
}

it('a root comment reports the page it is on', function () {
    config(['orbita.comments.per_page' => 5]);

    $post = deepLinkPost();
    $comments = [];
    foreach (range(1, 18) as $i) {
        $comments[$i] = deepLinkComment($post, 'comentario '.$i);
    }

    $service = app(CommentService::class);

    // Newest first: #18 leads page 1, #13 opens page 2, #3 opens page 4.
    expect($service->rootPageOf($comments[18], 'newest'))->toBe(1)
        ->and($service->rootPageOf($comments[13], 'newest'))->toBe(2)
        ->and($service->rootPageOf($comments[3], 'newest'))->toBe(4);

    // Oldest first flips the whole thing.
    expect($service->rootPageOf($comments[1], 'oldest'))->toBe(1)
        ->and($service->rootPageOf($comments[18], 'oldest'))->toBe(4);
});

/** A reply is rendered nested inside its root, so it lives on the root's page. */
it('a reply is located by its root', function () {
    config(['orbita.comments.per_page' => 5]);

    $post = deepLinkPost();
    $roots = [];
    foreach (range(1, 18) as $i) {
        $roots[$i] = deepLinkComment($post, 'comentario '.$i);
    }

    $reply = deepLinkComment($post, 'resposta funda', $roots[3]);
    $nested = deepLinkComment($post, 'resposta da resposta', $reply);

    $rootPage = app(CommentService::class)->rootPageOf($roots[3], 'newest');

    expect(app(CommentService::class)->rootPageOf($reply, 'newest'))->toBe($rootPage)
        ->and(app(CommentService::class)->rootPageOf($nested, 'newest'))->toBe($rootPage);
});

/** The permalink carries that page - and omits it on page one. */
it('the permalink carries the page only when it is needed', function () {
    config(['orbita.comments.per_page' => 5]);

    $post = deepLinkPost();
    $comments = [];
    foreach (range(1, 18) as $i) {
        $comments[$i] = deepLinkComment($post, 'comentario '.$i);
    }

    $service = app(CommentService::class);

    // A comment on page 1 needs no page parameter.
    expect($service->permalinkFor($comments[18], $post, 'newest'))->not->toContain('comentarios=');

    $deep = $service->permalinkFor($comments[3], $post, 'newest');
    expect($deep)->toContain('comentarios=4')
        ->toEndWith('#comment-'.$comments[3]->hashid);
});

/** And that page really does contain the comment - the point of the whole exercise. */
it('the page named by the permalink contains the comment', function () {
    config(['orbita.comments.per_page' => 5]);

    $post = deepLinkPost();
    $comments = [];
    foreach (range(1, 18) as $i) {
        $comments[$i] = deepLinkComment($post, 'comentario '.$i);
    }

    $target = $comments[3];
    $page = app(CommentService::class)->rootPageOf($target, 'newest');

    $url = route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]).'?comentarios='.$page;

    get($url)
        ->assertOk()
        ->assertSee('id="comment-'.$target->hashid.'"', false);
});

/** An orphaned comment (its parent was removed) is promoted to a root and stays findable. */
it('an orphaned comment is located as a root', function () {
    config(['orbita.comments.per_page' => 5]);

    $post = deepLinkPost();
    foreach (range(1, 10) as $i) {
        deepLinkComment($post, 'comentario '.$i);
    }

    $parent = deepLinkComment($post, 'pai que sera removido');
    $orphan = deepLinkComment($post, 'filho orfao', $parent);

    $parent->update(['status' => 'revision']);

    $page = app(CommentService::class)->rootPageOf($orphan->fresh(), 'newest');

    expect($page)->toBeGreaterThanOrEqual(1);

    get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]).'?comentarios='.$page)
        ->assertOk()
        ->assertSee('id="comment-'.$orphan->hashid.'"', false);
});
