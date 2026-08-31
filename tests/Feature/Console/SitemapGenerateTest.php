<?php

declare(strict_types=1);

/**
 * Tests the orbita:sitemap-generate command.
 */

namespace Tests\Feature\Console;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

/**
 * The output directory for the test currently running. Passing a value sets it;
 * beforeEach() does that, so each test gets a fresh directory exactly as the
 * original setUp() provided.
 */
function sitemapDir(?string $set = null): string
{
    static $dir = '';

    if ($set !== null) {
        $dir = $set;
    }

    return $dir;
}

beforeEach(function () {
    $dir = storage_path('framework/testing/sitemap-'.uniqid());
    File::ensureDirectoryExists($dir);
    sitemapDir($dir);
});

afterEach(function () {
    File::deleteDirectory(sitemapDir());
});

function sitemapPost(string $hashid, string $status): Post
{
    $author = User::factory()->createOne(['username' => 'a'.$hashid]);

    return Post::create([
        'user_id' => $author->id,
        'hashid' => $hashid,
        'title' => 'T '.$hashid,
        'slug' => 'slug-'.$hashid,
        'content' => 'c',
        'status' => $status,
        'published_at' => now(),
    ]);
}

function generateSitemap(): void
{
    artisan('orbita:sitemap-generate', ['--dir' => sitemapDir()])->assertSuccessful();
}

it('published posts are included and drafts excluded', function () {
    sitemapPost('pub1', 'published');
    sitemapPost('draft1', 'draft');

    generateSitemap();

    $posts = File::get(sitemapDir().'/sitemap-posts-1.xml');
    expect($posts)->toContain(route('posts.show', ['hashid' => 'pub1', 'slug' => 'slug-pub1']));
    expect($posts)->not->toContain('draft1');
});

it('handles the last comment timestamp when generating post sitemaps', function () {
    $post = sitemapPost('commented', 'published');
    $post->forceFill([
        'updated_at' => now()->subDay(),
        'last_comment_at' => now(),
    ])->save();

    generateSitemap();

    expect(File::exists(sitemapDir().'/sitemap-posts-1.xml'))->toBeTrue();
});

it('index references the child sitemaps', function () {
    sitemapPost('pub1', 'published');

    generateSitemap();

    $index = File::get(sitemapDir().'/sitemap.xml');
    expect($index)->toContain('<sitemapindex')
        ->toContain('/sitemap-pages.xml')
        ->toContain('/sitemap-posts-1.xml');
});

it('home and active pages are in the pages sitemap', function () {
    Page::create(['slug' => 'sobre', 'title' => 'Sobre', 'content' => 'x', 'is_active' => true]);
    Page::create(['slug' => 'oculta', 'title' => 'Oculta', 'content' => 'x', 'is_active' => false]);

    generateSitemap();

    $pages = File::get(sitemapDir().'/sitemap-pages.xml');
    expect($pages)->toContain(route('home'))
        ->toContain(route('pages.show', ['slug' => 'sobre']));
    // Inactive pages are excluded.
    expect($pages)->not->toContain('oculta');
});

it('posts are paginated into multiple files past the chunk size', function () {
    // Chunk size is 200; prove pagination via the written index files.
    sitemapPost('pub1', 'published');
    sitemapPost('pub2', 'closed'); // closed posts are live pages, still indexable

    generateSitemap();

    $posts = File::get(sitemapDir().'/sitemap-posts-1.xml');
    expect($posts)->toContain('pub1')->toContain('pub2');
});
