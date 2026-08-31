<?php

declare(strict_types=1);

/**
 * Infinite scroll mechanics: loadMore serves one page as an island fragment to append.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function seedIslandPosts(int $n): void
{
    $author = User::factory()->createOne(['username' => 'autor_island', 'email_verified_at' => now()]);

    foreach (range(1, $n) as $i) {
        Post::create([
            'user_id' => $author->id,
            'hashid' => 'isl'.$i,
            'title' => 'Post numero '.$i,
            'slug' => 'post-numero-'.$i,
            'content' => 'corpo',
            'status' => 'published',
            'allow_comments' => true,
            'published_at' => now()->subMinutes($n - $i),
        ]);
    }
}

/** The rendered feed declares the two islands the client needs to find later. */
it('the feed renders named island fragments', function () {
    seedIslandPosts(60);

    $html = (string) get('/all')->assertOk()->getContent();

    expect($html)->toContain('name=feed-rows')  // the rows island must be addressable
        ->toContain('name=feed-nav')
        // The load-more button carries the append metadata; without it the response replaces the list.
        ->toContain('wire:island.append="feed-rows"');
});

/** loadMore advances one page and serves only that page. */
it('load more serves only the next page', function () {
    seedIslandPosts(60);

    $titles = fn ($paginator) => array_map(fn ($p) => $p->title, $paginator->items());

    $component = Livewire::test('home-feed', ['tab' => 'all']);

    $first = $titles($component->instance()->posts);
    expect($first)->toContain('Post numero 60');  // page 1 holds the newest posts
    expect($first)->not->toContain('Post numero 35');

    $component->call('loadMore', 'page');

    $second = $titles($component->instance()->posts);
    expect($component->instance()->posts->currentPage())->toBe(2)
        ->and($second)->toContain('Post numero 35')
        ->and(array_intersect($first, $second))->toBe([], 'no post is served twice');
});

/** The nav comes back as a second, morphing fragment so the sentinel moves down. */
it('load more also returns the nav fragment', function () {
    seedIslandPosts(60);

    $component = Livewire::test('home-feed', ['tab' => 'all'])->call('loadMore', 'page');

    $fragments = implode('', $component->effects['islandFragments'] ?? []);

    expect($fragments)->not->toBeEmpty('the nav has to be re-rendered or the sentinel stays on page 1');
    expect($fragments)->toContain('name=feed-nav')
        ->toContain('mode=morph')
        ->toContain('page=3');  // the nav now points one page further on
});

/** A new search query morphs the rows island back to page one. */
it('a new search query morphs the rows island back to page one', function () {
    seedIslandPosts(30);

    $component = Livewire::test('search')
        ->set('query', 'Post')
        ->call('loadMore', 'page');

    expect($component->instance()->results->currentPage())->toBe(2);

    $component->set('query', 'numero 3');

    $fragments = implode('', $component->effects['islandFragments'] ?? []);

    expect($component->instance()->results->currentPage())->toBe(1, 'a new query starts over');
    expect($fragments)->toContain('name=search-rows')
        ->toContain('mode=morph')          // the appended rows are replaced, not added to
        ->toContain('name=search-summary'); // the result count follows the query
});
