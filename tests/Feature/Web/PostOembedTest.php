<?php

declare(strict_types=1);

/**
 * The post page oEmbed path: inline render, cache, and plain-link fallback.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

const YOUTUBE_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

const TWEET_URL = 'https://x.com/nasa/status/123456789';

/**
 * The author for the test currently running; beforeEach() reseeds it.
 */
function oembedAuthor(?User $set = null): User
{
    static $author = null;

    if ($set instanceof User) {
        $author = $set;
    }

    return $author;
}

beforeEach(function () {
    seed(ReactionTypeSeeder::class);
    oembedAuthor(User::factory()->createOne(['email_verified_at' => now()]));

    foreach (['youtube.com', 'x.com', 'exemplo.com.br'] as $host) {
        Cache::put('favicon:'.$host, '0', now()->addHour());
    }
});

function oembedLinkPost(string $url, int $id = 1): Post
{
    return Post::create([
        'user_id' => oembedAuthor()->id,
        'hashid' => HashId::encode($id),
        'title' => 'Um link',
        'slug' => 'um-link',
        'url' => $url,
        'content' => '',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('a video oembed renders its html inline inside a 16 9 frame', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response([
            'type' => 'video',
            'html' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="200" height="113"></iframe>',
        ]),
    ]);

    $post = oembedLinkPost(YOUTUBE_URL);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->toContain('<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"')
        ->toContain('oembed-frame')
        ->toContain('aspect-video');
    // The frame does not replace the link row: the source stays reachable below it.
    expect($html)->toContain('href="'.YOUTUBE_URL.'"')
        ->toContain('youtube.com');
});

/** The reader sees the embed first, then the actions for the same URL. */
it('the embed is rendered above the link button row', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response([
            'type' => 'video',
            'html' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
        ]),
    ]);

    $post = oembedLinkPost(YOUTUBE_URL);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    $frameAt = strpos($html, 'oembed-frame');
    $buttonAt = strpos($html, 'href="'.YOUTUBE_URL.'"');

    expect($frameAt)->not->toBeFalse()
        ->and($buttonAt)->not->toBeFalse()
        ->and($frameAt)->toBeLessThan($buttonAt);
});

it('a rich oembed is not forced into the 16 9 frame', function () {
    Http::fake([
        'https://publish.x.com/oembed*' => Http::response([
            'type' => 'rich',
            'html' => '<blockquote class="twitter-tweet">Tweet da NASA</blockquote>',
        ]),
    ]);

    $post = oembedLinkPost(TWEET_URL);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->toContain('Tweet da NASA')->toContain('oembed-frame');
    // A tweet carries its own height; a 16:9 box would crop it.
    expect($html)->not->toContain('aspect-video');
});

it('the provider response is cached and not refetched on the next pageview', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response([
            'type' => 'video',
            'html' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
        ]),
    ]);

    $post = oembedLinkPost(YOUTUBE_URL);

    get('/p/'.$post->hashid)->assertOk();
    assertDatabaseHas('oembed_cache', ['url_hash' => md5(YOUTUBE_URL)]);

    get('/p/'.$post->hashid)->assertOk()->assertSee('youtube.com/embed', false);
    Http::assertSentCount(1);
});

/** An expired cache row is refetched and written with a fresh expiry. */
it('an expired cache row is refetched and refreshed', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response([
            'type' => 'video',
            'html' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
        ]),
    ]);

    $post = oembedLinkPost(YOUTUBE_URL);

    DB::table('oembed_cache')->insert([
        'url' => YOUTUBE_URL,
        'url_hash' => md5(YOUTUBE_URL),
        'oembed_data' => json_encode(['type' => 'video', 'html' => '<iframe src="https://stale.example/x"></iframe>']),
        'expires_at' => now()->subDay(),
        'created_at' => now()->subDays(10),
    ]);

    get('/p/'.$post->hashid)->assertOk()->assertSee('youtube.com/embed', false);

    Http::assertSentCount(1);

    $row = DB::table('oembed_cache')->where('url_hash', md5(YOUTUBE_URL))->first();
    expect(Carbon::parse($row->expires_at)->isFuture())->toBeTrue('a refresh must write a new expires_at');
    expect((string) $row->oembed_data)->toContain('dQw4w9WgXcQ');
    expect((string) $row->oembed_data)->not->toContain('stale.example');
});

it('a url with no matching provider shows the plain link and calls nobody', function () {
    Http::fake();

    $post = oembedLinkPost('https://exemplo.com.br/um-artigo');

    get('/p/'.$post->hashid)
        ->assertOk()
        ->assertSee('exemplo.com.br');

    Http::assertNothingSent();
    assertDatabaseCount('oembed_cache', 0);
});

it('a failing provider degrades to the plain link', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response('boom', 500),
    ]);

    $post = oembedLinkPost(YOUTUBE_URL);

    get('/p/'.$post->hashid)
        ->assertOk()
        ->assertSee('youtube.com')
        ->assertDontSee('oembed-frame');

    // The failure is remembered, but never as a usable payload.
    $row = DB::table('oembed_cache')->where('url_hash', md5(YOUTUBE_URL))->first();
    expect($row)->not->toBeNull();
    expect(json_decode((string) $row->oembed_data, true))->toBe([]);
});

/** A remembered provider failure is not retried on every render. */
it('a failing provider is not retried on every render', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response('boom', 500),
    ]);

    $post = oembedLinkPost(YOUTUBE_URL);

    get('/p/'.$post->hashid)->assertOk();
    get('/p/'.$post->hashid)->assertOk();
    get('/p/'.$post->hashid)->assertOk();

    Http::assertSentCount(1);
});

/** A remembered failure expires and is retried after the short TTL. */
it('a remembered failure expires and is retried', function () {
    // One stateful stub: Http::fake() merges stubs, so a second call would not override the 500.
    $failing = true;

    Http::fake(function () use (&$failing) {
        return $failing
            ? Http::response('boom', 500)
            : Http::response([
                'type' => 'video',
                'html' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
            ]);
    });

    $post = oembedLinkPost(YOUTUBE_URL);
    get('/p/'.$post->hashid)->assertOk();
    Http::assertSentCount(1);

    // Age the remembered failure past its window; the provider comes back up.
    DB::table('oembed_cache')
        ->where('url_hash', md5(YOUTUBE_URL))
        ->update(['expires_at' => now()->subMinute()]);
    $failing = false;

    get('/p/'.$post->hashid)->assertOk()->assertSee('youtube.com/embed', false);

    $row = DB::table('oembed_cache')->where('url_hash', md5(YOUTUBE_URL))->first();
    expect((string) $row->oembed_data)->toContain('dQw4w9WgXcQ');
});
