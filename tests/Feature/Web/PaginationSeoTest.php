<?php

declare(strict_types=1);

/**
 * Pagination metadata a crawler receives: canonical, prev, next.
 */

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Monotonic across the file, so hashids never collide between tests.
 */
function seoSeq(): int
{
    static $n = 0;

    return ++$n;
}

function makeSeoPosts(int $n, ?Term $tag = null): User
{
    $author = User::factory()->createOne(['username' => 'autor_seo', 'email_verified_at' => now()]);

    foreach (range(1, $n) as $i) {
        $post = Post::create([
            'user_id' => $author->id,
            'hashid' => 'seo'.seoSeq(),
            'title' => 'Post numero '.$i,
            'slug' => 'post-numero-'.$i,
            'content' => 'corpo',
            'status' => 'published',
            'allow_comments' => true,
            'published_at' => now()->subMinutes($n - $i),
        ]);

        $tag?->posts()->attach($post->id);
    }

    return $author;
}

function headOf(string $url): string
{
    $html = (string) get($url)->assertOk()->getContent();

    return substr($html, 0, (int) strpos($html, '</head>'));
}

/** Page 1 announces a next page and claims the bare URL. */
it('the first feed page is self canonical and points forward', function () {
    makeSeoPosts(60);

    $head = headOf('/all');

    expect($head)->toContain('<link rel="canonical" href="'.url('/all').'">')
        ->toContain('<link rel="next" href="'.url('/all').'?page=2">');
    // Page 1 has nothing before it.
    expect($head)->not->toContain('rel="prev"');
});

/** A middle page claims itself, not page 1, and points both ways. */
it('a middle feed page canonicalises to itself', function () {
    makeSeoPosts(100);

    $head = headOf('/all?page=3');

    expect($head)->toContain('<link rel="canonical" href="'.url('/all').'?page=3">')
        ->toContain('<link rel="prev" href="'.url('/all').'?page=2">')
        ->toContain('<link rel="next" href="'.url('/all').'?page=4">');
});

/** The last page must not advertise a page that does not exist. */
it('the last page offers no next', function () {
    makeSeoPosts(30);   // 25 per page => 2 pages

    $head = headOf('/all?page=2');

    expect($head)->toContain('rel="prev"');
    // There is no page 3.
    expect($head)->not->toContain('rel="next"');
});

it('a tag page carries the same pagination metadata', function () {
    $tag = Term::create(['taxonomy' => 'tag', 'name' => 'Ciencia', 'slug' => 'ciencia', 'is_active' => true]);
    makeSeoPosts(60, $tag);

    $url = route('tags.show', ['slug' => 'ciencia']);
    $head = headOf($url.'?page=2');

    expect($head)->toContain('<link rel="canonical" href="'.$url.'?page=2">')
        ->toContain('<link rel="prev" href="'.$url.'">')   // back to page 1 is the bare URL
        ->toContain('<link rel="next" href="'.$url.'?page=3">');
});

/** Comment pages are canonical but ?sort= orderings are not. */
it('a comment page is canonical but a sort is not', function () {
    config(['orbita.comments.per_page' => 5]);

    $author = makeSeoPosts(1);
    $post = Post::query()->firstOrFail();

    foreach (range(1, 18) as $i) {
        Comment::create([
            'user_id' => $author->id, 'post_id' => $post->id, 'parent_id' => null,
            'hashid' => 'sc'.$i, 'content' => 'comentario '.$i,
            'status' => 'visible', 'nesting_level' => 0,
        ]);
    }

    $url = route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);

    expect(headOf($url.'?comentarios=2'))
        ->toContain('<link rel="canonical" href="'.$url.'?comentarios=2">')
        ->toContain('<link rel="prev" href="'.$url.'">')
        ->toContain('<link rel="next" href="'.$url.'?comentarios=3">');

    // An ordering is not a distinct page and must canonicalise to the post.
    expect(headOf($url.'?sort=oldest'))->toContain('<link rel="canonical" href="'.$url.'">');
});

/** Private auth-only lists are noindex. */
it('the private lists are noindex', function () {
    $reader = User::factory()->createOne(['username' => 'leitor_seo', 'email_verified_at' => now()]);

    foreach ([route('bookmarks.index'), route('notifications.index')] as $url) {
        $head = (string) actingAs($reader)->get($url)->assertOk()->getContent();

        expect($head)->toContain('<meta name="robots" content="noindex, nofollow">');
    }
});
