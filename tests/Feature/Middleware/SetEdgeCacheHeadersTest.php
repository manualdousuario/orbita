<?php

declare(strict_types=1);

/**
 * Tests the edge Cache-Control headers set by the middleware.
 */

namespace Tests\Feature\Middleware;

use App\Models\Post;
use App\Models\User;
use App\Support\HomeFeedTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('anonymous get on home sets public edge cache control', function () {
    $response = get('/');

    $response->assertOk();

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=30')
        ->toContain('s-maxage=60')
        ->toContain('stale-while-revalidate=600')
        ->toContain('stale-if-error=86400');
});

it('a deeper feed page is cached like the first', function () {
    $response = get('/all?page=2');

    $response->assertOk();

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('s-maxage=60');
});

it('every home feed tab is tagged, none left behind', function (string $path) {
    $response = get($path);

    $response->assertOk();

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('s-maxage=60');
})->with(array_column(HomeFeedTabs::all(), 'path'));

it('authenticated get on home is marked private no store', function () {
    $user = User::factory()->createOne();

    $response = actingAs($user)->get('/');

    $response->assertOk();

    $cc = (string) $response->headers->get('Cache-Control');
    expect($cc)->toContain('private')->toContain('no-store');
    expect($cc)->not->toContain('s-maxage=');
});

it('custom ttls from config are respected', function () {
    config()->set('orbita.cache.browser_max_age', 11);
    config()->set('orbita.cache.edge_max_age', 22);
    config()->set('orbita.cache.stale_while_revalidate', 33);
    config()->set('orbita.cache.stale_if_error', 44);

    $response = get('/');

    $response->assertOk();

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('max-age=11')
        ->toContain('s-maxage=22')
        ->toContain('stale-while-revalidate=33')
        ->toContain('stale-if-error=44');
});

it('post show route is tagged for anonymous visitors', function () {
    $author = User::factory()->createOne([
        'username' => 'astronauta',
        'email_verified_at' => now(),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => 'h1',
        'title' => 'Post publico',
        'slug' => 'post-publico',
        'content' => 'corpo',
        'status' => 'published',
        'score' => 1,
        'comment_count' => 0,
        'published_at' => now()->subHour(),
    ]);

    $response = get('/p/h1/post-publico');

    $response->assertOk();

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('s-maxage=60');
});

it('404 response is not tagged', function () {
    $response = get('/p/inexistente/nao-existe');

    $response->assertNotFound();

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('s-maxage=60');
});

it('login page is not tagged', function () {
    // /login is served by Fortify and is NOT in the cache.control middleware list.
    $response = get('/login');

    $response->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('s-maxage=60');
});
