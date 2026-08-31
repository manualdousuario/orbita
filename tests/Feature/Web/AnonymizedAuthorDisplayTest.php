<?php

declare(strict_types=1);

/**
 * Deleted accounts render as "Conta excluída" with no handle or profile link.
 */

namespace Tests\Feature\Web;

use App\Actions\AnonymizeUser;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Post, 2: Comment}
 */
function anonymizedAuthorWithContent(): array
{
    $author = User::factory()->createOne([
        'username' => 'joana_dev',
        'display_name' => 'Joana Dev',
        'email' => 'joana@example.com',
        'email_verified_at' => now(),
    ]);

    $post = Post::create([
        'user_id' => $author->id, 'hashid' => 'anon1', 'title' => 'Post que permanece',
        'slug' => 'post-que-permanece', 'content' => 'corpo do post', 'status' => 'published',
        'allow_comments' => true, 'published_at' => now(),
    ]);

    $comment = Comment::create([
        'user_id' => $author->id, 'post_id' => $post->id, 'hashid' => 'anonc1',
        'content' => 'comentário que permanece', 'status' => 'visible', 'nesting_level' => 0,
    ]);

    app(AnonymizeUser::class)->handle($author);

    return [$author->fresh(), $post, $comment];
}

it('post page shows the placeholder and no profile link', function () {
    [$author, $post] = anonymizedAuthorWithContent();

    $response = get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()
        ->assertSee('Post que permanece')
        ->assertSee(AnonymizeUser::DISPLAY_NAME);

    $response->assertDontSee('Joana Dev');
    $response->assertDontSee('joana_dev');
    $response->assertDontSee(route('users.profile', ['username' => $author->username]));
});

it('comment thread shows the placeholder and no profile link', function () {
    [$author, $post] = anonymizedAuthorWithContent();

    $response = get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()
        ->assertSee('comentário que permanece');

    $response->assertDontSee('joana_dev');
    $response->assertDontSee(route('users.profile', ['username' => $author->username]));
});

/** The raw-SQL home feed path shows the placeholder and hides the old handle. */
it('home feed shows the placeholder and no handle', function () {
    [$author] = anonymizedAuthorWithContent();

    $response = get(route('home'))
        ->assertOk()
        ->assertSee('Post que permanece')
        ->assertSee(AnonymizeUser::DISPLAY_NAME);

    $response->assertDontSee('Joana Dev');
    $response->assertDontSee('@'.$author->username, false);
    $response->assertDontSee(route('users.profile', ['username' => $author->username]));
});

it('search results do not expose the old identity', function () {
    anonymizedAuthorWithContent();

    get(route('search', ['q' => 'permanece']))
        ->assertOk()
        ->assertDontSee('Joana Dev')
        ->assertDontSee('joana_dev');
});

it('both the old and the placeholder profile urls 404', function () {
    [$author] = anonymizedAuthorWithContent();

    get('/u/joana_dev')->assertNotFound();
    get(route('users.profile', ['username' => $author->username]))->assertNotFound();
});

it('settings pages are unreachable for a deleted account', function () {
    [$author] = anonymizedAuthorWithContent();

    actingAs($author)
        ->get(route('users.edit', ['username' => $author->username]))
        ->assertNotFound();
});

it('login with the old credentials fails', function () {
    $author = User::factory()->createOne([
        'username' => 'login_excluido',
        'email' => 'login@example.com',
        'password' => 'senha-secreta',
        'email_verified_at' => now(),
    ]);

    app(AnonymizeUser::class)->handle($author);

    post(route('login.store'), ['email' => 'login@example.com', 'password' => 'senha-secreta'])
        ->assertSessionHasErrors();

    assertGuest();
});

it('a deleted account does not appear in the mention typeahead', function () {
    anonymizedAuthorWithContent();
    $searcher = User::factory()->createOne(['username' => 'quem_busca', 'email_verified_at' => now()]);

    actingAs($searcher);

    getJson(route('users.mention-search', ['q' => 'joana']))
        ->assertOk()
        ->assertJsonCount(0);
});

it('the email and username are freed for a new registration', function () {
    anonymizedAuthorWithContent();

    expect(User::where('email', 'joana@example.com')->count())->toBe(0)
        ->and(User::where('username', 'joana_dev')->count())->toBe(0);
});
