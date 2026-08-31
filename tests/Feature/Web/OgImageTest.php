<?php

declare(strict_types=1);

/**
 * On-demand OG image rendering that is never persisted.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Assert;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function ogPost(array $overrides = []): Post
{
    return Post::create(array_merge([
        'user_id' => User::factory()->createOne(['email_verified_at' => now()])->id,
        'hashid' => HashId::encode(1),
        'title' => 'Buracos negros',
        'slug' => 'buracos-negros',
        'content' => 'Um corpo de post com **markdown**.',
        'status' => 'published',
        'published_at' => now(),
    ], $overrides));
}

function ogImageUrlFor(Post $post): string
{
    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    Assert::assertSame(1, preg_match('/<meta property="og:image" content="([^"]+)">/', $html, $matches));

    return html_entity_decode($matches[1]);
}

it('the route renders a jpeg and stores nothing', function () {
    $post = ogPost();

    $response = get(route('posts.og', ['hashid' => $post->hashid]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');

    expect((string) $response->getContent())->toStartWith("\xFF\xD8\xFF");

    // No media row, no pivot row, no file.
    assertDatabaseCount('media', 0);
    assertDatabaseCount('media_relationship', 0);
});

/** The render handoff works on the database cache store. */
it('the render handoff survives the database cache store', function () {
    config(['cache.default' => 'database']);
    $post = ogPost();

    $first = get(route('posts.og', ['hashid' => $post->hashid]));
    $first->assertOk();

    // Second request inside the handoff window reads the value back from the store.
    $second = get(route('posts.og', ['hashid' => $post->hashid]));
    $second->assertOk();

    expect($second->getContent())->toBe($first->getContent());
    expect((string) $second->getContent())->toStartWith("\xFF\xD8\xFF");
});

it('the response is cacheable by a cdn', function () {
    $post = ogPost();

    // Symfony normalises Cache-Control into alphabetical order.
    get(route('posts.og', ['hashid' => $post->hashid]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
});

it('a post page points og image at a stable url', function () {
    $post = ogPost();

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    $expected = route('posts.og', ['hashid' => $post->hashid]);

    expect($html)->toContain('<meta property="og:image" content="'.e($expected).'">');

    // No render happened while building the page.
    assertDatabaseCount('media', 0);
});

/** Editing a post must not change the og:image URL (no ?v=). */
it('editing a post does not change the og image url', function () {
    $post = ogPost();

    $before = ogImageUrlFor($post);

    $post->forceFill(['title' => 'Buracos brancos', 'updated_at' => $post->updated_at->addDay()])->save();

    expect(ogImageUrlFor($post->fresh()))->toBe($before);
    expect($before)->not->toContain('?');
});

it('an unknown or unpublished post 404s before rendering', function () {
    get(route('posts.og', ['hashid' => 'naoexiste']))->assertNotFound();

    $draft = ogPost(['hashid' => HashId::encode(2), 'status' => 'draft']);
    get(route('posts.og', ['hashid' => $draft->hashid]))->assertNotFound();

    $revision = ogPost(['hashid' => HashId::encode(3), 'version_of' => ogPost(['hashid' => HashId::encode(4)])->id]);
    get(route('posts.og', ['hashid' => $revision->hashid]))->assertNotFound();
});

it('the route is rate limited', function () {
    $post = ogPost();
    $url = route('posts.og', ['hashid' => $post->hashid]);

    $middleware = collect(app('router')->getRoutes()->getByName('posts.og')->gatherMiddleware());

    expect($middleware->contains('throttle:30,1'))->toBeTrue(
        'A render on a public endpoint must be rate limited; got: '.$middleware->implode(', '),
    );
    expect($url)->not->toBeEmpty();
});

/** The og route is not swallowed by the /s/ stored-media catch-all. */
it('the image route is not swallowed by the stored media route', function () {
    $post = ogPost();

    expect(parse_url(route('posts.og', ['hashid' => $post->hashid]), PHP_URL_PATH))
        ->toBe('/s/og/'.$post->hashid.'.jpg');

    get('/s/og/'.$post->hashid.'.jpg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

/** The route only answers with the .jpg suffix. */
it('the route does not answer without the jpg suffix', function () {
    $post = ogPost();

    get('/s/og/'.$post->hashid)->assertNotFound();
});
