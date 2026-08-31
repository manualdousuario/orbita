<?php

declare(strict_types=1);

/**
 * Every paginated list is crawlable without JavaScript, each page a unique URL.
 */

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Monotonic across the file, so hashids never collide between tests.
 */
function paginationSeq(): int
{
    static $n = 0;

    return ++$n;
}

function makePaginatedPosts(int $n, ?Term $tag = null): User
{
    $author = User::factory()->createOne(['username' => 'autor_pag', 'email_verified_at' => now()]);

    foreach (range(1, $n) as $i) {
        $post = Post::create([
            'user_id' => $author->id,
            'hashid' => 'pg'.paginationSeq(),
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

/** No surface may go back to wire:click pagination: a crawler cannot press a button. */
it('no page renders wire click pagination', function () {
    $author = makePaginatedPosts(60);

    foreach (['/', '/all', route('users.profile', ['username' => $author->username])] as $url) {
        $html = (string) get($url)->assertOk()->getContent();

        expect($html)->not->toContain('gotoPage(');
        expect($html)->not->toContain('wire:click="nextPage');
    }
});

/** The feed offers a real link to page 2, and page 2 answers with different posts. */
it('the feed links to the next page and that page serves different posts', function () {
    makePaginatedPosts(60);

    $first = (string) get('/all')->assertOk()->getContent();

    expect($first)->toMatch('/<a [^>]*rel="next"[^>]*>/');
    expect($first)->toContain('/all?page=2')
        ->toContain('Post numero 60'); // page 1 holds the newest posts
    expect($first)->not->toContain('Post numero 10');

    $second = (string) get('/all?page=2')->assertOk()->getContent();

    // Page 2 is not page 1 again.
    expect($second)->not->toContain('Post numero 60');
    expect($second)->toContain('Post numero 35')->toContain('rel="prev"');
});

/** Pagination hrefs must be absolute: a relative one resolves against the wrong base. */
it('pagination links are absolute', function () {
    $tag = Term::create(['taxonomy' => 'tag', 'name' => 'Tecnologia', 'slug' => 'tecnologia', 'is_active' => true]);
    makePaginatedPosts(60, $tag);

    $html = (string) get(route('tags.show', ['slug' => 'tecnologia']))->assertOk()->getContent();

    preg_match_all('/<nav[^>]*data-pagination-nav.*?<\/nav>/s', $html, $navs);
    expect($navs[0])->not->toBeEmpty('the tag page renders a pagination nav');

    preg_match_all('/href="([^"]*page=\d+[^"]*)"/', $navs[0][0], $hrefs);
    expect($hrefs[1])->not->toBeEmpty();

    foreach ($hrefs[1] as $href) {
        expect($href)->toStartWith('http', "relative pagination href resolves against the wrong base: {$href}");
    }
});

/** Page one is the bare URL. ?page=1 would be a second URL for identical content. */
it('the first page link carries no page parameter', function () {
    makePaginatedPosts(60);

    $html = (string) get('/all?page=3')->assertOk()->getContent();

    preg_match('/<nav[^>]*data-pagination-nav.*?<\/nav>/s', $html, $nav);
    expect($nav)->not->toBeEmpty();
    // The link to page 1 must not be ?page=1.
    expect($nav[0])->not->toContain('page=1"');
});

/** The numbered nav stays in the HTML, hidden by CSS only for JS readers. */
it('the numbered nav is present but marked hidden for js readers', function () {
    makePaginatedPosts(60);

    $html = (string) get('/all')->assertOk()->getContent();

    expect($html)->toContain('data-pagination-nav')
        ->toContain('js-hidden')  // the nav is hidden by CSS, not dropped
        ->toContain('js-only');   // the load-more sentinel only appears with JS
});

/** The two profile lists paginate independently. */
it('the profile columns paginate independently', function () {
    $author = makePaginatedPosts(30);
    $post = Post::query()->first();

    foreach (range(1, 30) as $i) {
        Comment::create([
            'user_id' => $author->id,
            'post_id' => $post->id,
            'parent_id' => null,
            'hashid' => 'pc'.$i,
            'content' => 'comentario de perfil '.$i,
            'status' => 'visible',
            'nesting_level' => 0,
        ]);
    }

    $url = route('users.profile', ['username' => $author->username]);
    $html = (string) get($url.'?posts=2')->assertOk()->getContent();

    expect($html)->toContain('posts=3')    // the posts column advances on its own key
        ->toContain('comments=2');          // the comments column is still on page 1
    // Neither column may fall back to the shared "page" key.
    expect($html)->not->toContain('?page=');
});
