<?php

declare(strict_types=1);

/**
 * The server-side bookmark toggle endpoint that replaced the per-row Livewire button.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function bookmarkablePost(): Post
{
    return Post::create([
        'user_id' => User::factory()->createOne()->id,
        'hashid' => HashId::unique('posts'),
        'title' => 'Um post salvável',
        'slug' => 'um-post-salvavel',
        'content' => 'corpo',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('toggling saves then unsaves and reports the new state', function () {
    $user = User::factory()->createOne();
    $post = bookmarkablePost();

    actingAs($user)
        ->postJson(route('bookmarks.toggle', ['hashid' => $post->hashid]))
        ->assertOk()
        ->assertJson(['bookmarked' => true]);

    assertDatabaseHas('bookmarks', ['user_id' => $user->id, 'post_id' => $post->id]);

    postJson(route('bookmarks.toggle', ['hashid' => $post->hashid]))
        ->assertOk()
        ->assertJson(['bookmarked' => false]);

    assertDatabaseCount('bookmarks', 0);
});

it('a guest is sent to login and saves nothing', function () {
    $post = bookmarkablePost();

    post(route('bookmarks.toggle', ['hashid' => $post->hashid]))
        ->assertRedirectContains(route('login'));

    assertDatabaseCount('bookmarks', 0);
});

/** An unknown or unpublished post returns 404. */
it('an unknown post is not found', function () {
    actingAs(User::factory()->createOne())
        ->postJson(route('bookmarks.toggle', ['hashid' => 'naoexiste']))
        ->assertNotFound();

    assertDatabaseCount('bookmarks', 0);
});

it('the bookmark button stays visible and labelled without alpine', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    $post = bookmarkablePost();

    $html = (string) actingAs($user)
        ->get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    preg_match('/<button[^>]*x-data="bookmarkButton.*?<\/button>/s', $html, $m);

    expect($m)->not->toBeEmpty('a verified user gets the interactive toggle');

    $button = $m[0];

    expect($button)->toContain('aria-label="Acompanhar post"')
        ->toContain('aria-pressed="false"');

    expect($button)->not->toContain('x-cloak');
    expect($button)->not->toContain('x-show');
    expect($button)->not->toContain('x-bind:aria-');

    preg_match_all('/<svg[^>]*>/', $button, $icons);

    $visible = array_filter($icons[0], function (string $svg): bool {
        preg_match('/(?<![\w:-])class="([^"]*)"/', $svg, $class);

        return ! in_array('hidden', preg_split('/\s+/', trim($class[1] ?? '')) ?: [], true);
    });

    expect($icons[0])->toHaveCount(2)
        ->and($visible)->toHaveCount(1, 'the server already picks the icon that shows');
});

it('the feed no longer mounts a livewire component per row', function () {
    bookmarkablePost();

    $html = (string) get('/')->assertOk()->getContent();

    // wire:snapshot is what a mounted Livewire component serializes; the button must add none.
    expect($html)->not->toContain('bookmark-button');
});
