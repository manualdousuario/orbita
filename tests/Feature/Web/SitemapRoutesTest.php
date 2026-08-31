<?php

declare(strict_types=1);

/**
 * Sitemap route and cache behavior.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * The cache directory for the test currently running; beforeEach() sets it.
 */
function sitemapCacheDir(?string $set = null): string
{
    static $dir = '';

    if ($set !== null) {
        $dir = $set;
    }

    return $dir;
}

beforeEach(function () {
    $dir = storage_path('framework/testing/sitemap-'.uniqid());
    sitemapCacheDir($dir);
    config(['orbita.sitemap.cache_dir' => $dir]);
});

afterEach(function () {
    File::deleteDirectory(sitemapCacheDir());
});

function sitemapRoutePost(string $hashid): Post
{
    $author = User::factory()->createOne(['username' => 'a'.$hashid]);

    return Post::create([
        'user_id' => $author->id,
        'hashid' => $hashid,
        'title' => 'T '.$hashid,
        'slug' => 'slug-'.$hashid,
        'content' => 'c',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('index is built on the first request with a cold cache', function () {
    sitemapRoutePost('pub1');
    expect(File::exists(sitemapCacheDir().'/'.SitemapService::INDEX))->toBeFalse('cache starts cold');

    $response = get('/sitemap.xml');

    $response->assertOk();

    expect((string) $response->headers->get('Content-Type'))->toStartWith('application/xml');
    expect($response->streamedContent())->toContain('<sitemapindex');
    expect(File::exists(sitemapCacheDir().'/'.SitemapService::INDEX))->toBeTrue('the request warmed the cache');
});

it('index points at child routes that resolve', function () {
    sitemapRoutePost('pub1');

    $index = get('/sitemap.xml')->streamedContent();

    expect($index)->toContain(route('sitemap.pages'))
        ->toContain(route('sitemap.posts', ['page' => 1]));

    get('/sitemap-pages.xml')->assertOk();
    get('/sitemap-posts-1.xml')->assertOk();
});

it('post urls appear in the chunk and drafts do not', function () {
    sitemapRoutePost('pub1');
    $draft = sitemapRoutePost('draft1');
    $draft->update(['status' => 'draft']);

    $posts = get('/sitemap-posts-1.xml')->streamedContent();

    expect($posts)->toContain(route('posts.show', ['hashid' => 'pub1', 'slug' => 'slug-pub1']));
    expect($posts)->not->toContain('draft1');
});

it('a chunk beyond what exists is a 404', function () {
    sitemapRoutePost('pub1');

    get('/sitemap-posts-99.xml')->assertNotFound();
});

it('non numeric chunk does not match the route', function () {
    // whereNumber keeps the {page} segment from accepting path traversal or junk.
    get('/sitemap-posts-abc.xml')->assertNotFound();
});

it('stale post files from a larger run are pruned', function () {
    sitemapRoutePost('pub1');
    get('/sitemap.xml')->assertOk();

    // Simulate a previous, larger generation leaving chunk 2 behind.
    File::put(sitemapCacheDir().'/'.SitemapService::postsFile(2), '<urlset>stale</urlset>');
    artisan('orbita:sitemap-generate')->assertSuccessful();

    expect(File::exists(sitemapCacheDir().'/'.SitemapService::postsFile(2)))->toBeFalse();

    get('/sitemap-posts-2.xml')->assertNotFound();
});
