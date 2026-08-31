<?php

declare(strict_types=1);

/**
 * A pinned post leads only the tab configured as the site's main one.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HomeFeedTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

const PINNED = 'Aviso fixado da moderacao';

const HOT = 'Post quente com muitos comentarios';

/** The pinned post is the weakest row, so only is_pinned can put it on top. */
function seedPinnedPosts(): void
{
    $author = User::factory()->createOne(['email_verified_at' => now()]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => 'pin1',
        'title' => PINNED,
        'slug' => 'aviso-fixado-da-moderacao',
        'content' => 'Conteudo',
        'status' => 'published',
        'score' => 0,
        'comment_count' => 0,
        'allow_comments' => true,
        'is_pinned' => true,
        'published_at' => now()->subDays(3),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => 'hot1',
        'title' => HOT,
        'slug' => 'post-quente',
        'content' => 'Conteudo',
        'status' => 'published',
        'score' => 42,
        'comment_count' => 12,
        'allow_comments' => true,
        'is_pinned' => false,
        'published_at' => now()->subHour(),
    ]);
}

it('pinned post leads the main tab', function () {
    config(['orbita.home.default_tab' => 'all']);
    seedPinnedPosts();

    get('/all')
        ->assertOk()
        ->assertSeeInOrder([PINNED, HOT]);
});

it('pinned post does not lead the other tabs', function () {
    config(['orbita.home.default_tab' => 'all']);
    seedPinnedPosts();

    get('/more-comments')
        ->assertOk()
        ->assertSeeInOrder([HOT, PINNED]);
});

it('pin marker renders only in the main tab', function () {
    config(['orbita.home.default_tab' => 'all']);
    seedPinnedPosts();

    get('/all')->assertOk()->assertSee('Post fixado');
    get('/more-comments')->assertOk()->assertDontSee('Post fixado');
});

/** Changing the main tab moves both ordering and marker without a cache flush. */
it('changing the main tab moves the ordering and the marker', function () {
    config(['orbita.home.default_tab' => 'all']);
    seedPinnedPosts();

    get('/all')->assertOk()->assertSeeInOrder([PINNED, HOT])->assertSee('Post fixado');
    get('/more-comments')->assertOk()->assertSeeInOrder([HOT, PINNED]);

    config(['orbita.home.default_tab' => 'comments']);

    get('/more-comments')->assertOk()->assertSeeInOrder([PINNED, HOT])->assertSee('Post fixado');
    get('/all')->assertOk()->assertSeeInOrder([HOT, PINNED])->assertDontSee('Post fixado');
});

/** The home route resolves the main tab per request, not at route-registration time. */
it('root follows the configured main tab', function () {
    config(['orbita.home.default_tab' => 'all']);

    get('/')
        ->assertOk()
        ->assertSee(HomeFeedTabs::all()['all']['description']);
});
