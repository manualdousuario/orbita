<?php

declare(strict_types=1);

/**
 * An unactivated account can read everything but cannot write anything.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserToken;
use App\Notifications\VerifyEmailNotification;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function pendingUser(): User
{
    return User::factory()->unverified()->createOne();
}

function pendingPost(?User $author = null): Post
{
    $author ??= User::factory()->createOne();

    return Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(1),
        'title' => 'Buracos negros e horizontes de eventos',
        'slug' => 'buracos-negros-e-horizontes-de-eventos',
        'content' => 'Um texto sobre buracos negros.',
        'status' => 'published',
        'allow_comments' => true,
        'published_at' => now()->subHour(),
    ]);
}

// ---------------------------------------------------------------- reading

it('pending user can read home and post', function () {
    $post = pendingPost();

    actingAs(pendingUser())->get('/')->assertOk();
    get(route('posts.show', ['hashid' => $post->hashid]))->assertOk();
});

it('pending user can read own private areas', function () {
    $user = pendingUser();

    actingAs($user)->get(route('bookmarks.index'))->assertOk();
    get(route('notifications.index'))->assertOk();
    get(route('users.edit', ['username' => $user->username]))->assertOk();
});

/**
 * Marking notifications read only affects the user's own read state.
 */
it('pending user can mark notifications read', function () {
    actingAs(pendingUser())
        ->post(route('notifications.read-all'))
        ->assertRedirect();
});

// ------------------------------------------------------------ HTTP writes

it('pending user is redirected from post create', function () {
    actingAs(pendingUser())
        ->get(route('posts.create'))
        ->assertRedirect(route('verification.notice'));
});

it('pending user cannot store a post over http', function () {
    actingAs(pendingUser())
        ->post(route('posts.store'), [
            'title' => 'Tentativa de publicar sem ativar',
            'content' => 'Conteudo qualquer.',
        ])
        ->assertRedirect(route('verification.notice'));

    assertDatabaseCount('posts', 0);
});

it('pending user cannot delete a post over http', function () {
    $user = pendingUser();
    $post = pendingPost($user);

    actingAs($user)
        ->delete(route('posts.destroy', ['hashid' => $post->hashid]))
        ->assertRedirect(route('verification.notice'));

    assertDatabaseCount('posts', 1);
});

it('verified user reaches post create', function () {
    actingAs(User::factory()->createOne())
        ->get(route('posts.create'))
        ->assertOk();
});

// -------------------------------------------------------- Livewire writes

it('pending user cannot create a post via livewire', function () {
    Livewire::actingAs(pendingUser())
        ->test('post-composer')
        ->set('title', 'Um titulo que nao deve ser publicado')
        ->set('content', 'Um conteudo qualquer.')
        ->call('save')
        ->assertRedirect(route('verification.notice'));

    assertDatabaseCount('posts', 0);
});

it('pending user cannot comment via livewire', function () {
    $post = pendingPost();

    Livewire::actingAs(pendingUser())
        ->test('comment-form', ['postId' => $post->id])
        ->set('content', 'Um comentario que nao deve existir.')
        ->call('save')
        ->assertRedirect(route('verification.notice'));

    assertDatabaseCount('comments', 0);
});

it('pending user cannot react', function () {
    $post = pendingPost();

    Livewire::actingAs(pendingUser())
        ->test('reactions', ['type' => 'post', 'id' => $post->id])
        ->call('react', 'like')
        ->assertRedirect(route('verification.notice'));

    assertDatabaseCount('user_reactions', 0);
});

/** The bookmark button is plain Blade + Alpine now; the gate lives on the route. */
it('pending user cannot bookmark', function () {
    $post = pendingPost();

    actingAs(pendingUser());

    postJson(route('bookmarks.toggle', ['hashid' => $post->hashid]))
        ->assertForbidden()
        ->assertJsonPath('url', route('verification.notice'));

    assertDatabaseCount('bookmarks', 0);
});

it('pending user cannot report', function () {
    $post = pendingPost();

    Livewire::actingAs(pendingUser())
        ->test('reactions', ['type' => 'post', 'id' => $post->id])
        ->set('reason', 'spam')
        ->call('report')
        ->assertRedirect(route('verification.notice'));

    assertDatabaseCount('reports', 0);
});

it('verified user can still create a post via livewire', function () {
    Livewire::actingAs(User::factory()->createOne())
        ->test('post-composer')
        ->set('title', 'Um titulo perfeitamente publicavel')
        ->set('content', 'Um conteudo qualquer.')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseCount('posts', 1);
});

/**
 * The gate is opt-in per method so read-only Livewire calls keep working.
 */
it('pending user can still browse a comment thread', function () {
    $author = User::factory()->createOne();
    $post = pendingPost($author);

    Comment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'parent_id' => null,
        'hashid' => HashId::encode(1),
        'content' => 'Um comentario visivel para quem ainda nao ativou.',
    ]);

    Livewire::actingAs(pendingUser())
        ->withQueryParams(['sort' => 'oldest'])
        ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->assertNoRedirect()
        ->call('loadMore', 'comentarios')
        ->assertNoRedirect();
});

/** The guest guard the attribute absorbed still behaves as it always did. */
it('guest write still redirects to login', function () {
    $post = pendingPost();

    post(route('bookmarks.toggle', ['hashid' => $post->hashid]))
        ->assertRedirectContains(route('login'));

    assertDatabaseCount('bookmarks', 0);
});

// ----------------------------------------------------- ways to get active

it('resend from any page returns back with status', function () {
    Notification::fake();
    $user = pendingUser();

    actingAs($user);

    from('/')
        ->post(route('verification.send'))
        ->assertRedirect('/')
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('verification link activates the account', function () {
    $user = pendingUser();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes((int) config('auth.verification.expire')),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        absolute: false,
    );

    actingAs($user)->get($url)->assertRedirect();

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
});

/**
 * A link opened while logged out activates the account directly, with no login detour.
 */
it('verification link opened while logged out activates directly', function () {
    $user = User::factory()->unverified()->createOne([
        'email' => 'deslogado@example.com',
        'password' => 'super-secret-pass',
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes((int) config('auth.verification.expire')),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        absolute: false,
    );

    get($url)
        ->assertRedirect(route('home'))
        ->assertSessionHas('status', 'verification-completed');

    expect($user->fresh()?->email_verified_at)->not->toBeNull();

    assertAuthenticatedAs($user->fresh());
});

/** Completing a reset proves control of the mailbox, so it activates. */
it('password reset activates a pending account', function () {
    $user = User::factory()->unverified()->createOne(['email' => 'pendente@example.com']);
    $token = Password::broker()->createToken($user);

    post(route('password.update'), [
        'token' => $token,
        'email' => 'pendente@example.com',
        'password' => 'uma-senha-bem-longa',
        'password_confirmation' => 'uma-senha-bem-longa',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
});

/**
 * Email change (the escape hatch for a mistyped signup address) also activates.
 */
it('email change confirmation activates a pending account', function () {
    $user = User::factory()->unverified()->createOne(['email' => 'errado@example.com']);

    $token = bin2hex(random_bytes(16));
    UserToken::create([
        'user_id' => $user->id,
        'token' => UserToken::hashToken($token),
        'token_type' => 'email_change',
        'email' => 'certo@example.com',
        'is_used' => false,
        'request_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);

    get(route('users.email.confirm', ['token' => $token]))->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh?->email)->toBe('certo@example.com')
        ->and($fresh?->email_verified_at)->not->toBeNull();
});

it('activating lifts the write block', function () {
    $user = pendingUser();

    actingAs($user)->get(route('posts.create'))->assertRedirect(route('verification.notice'));

    $user->forceFill(['email_verified_at' => now()])->save();

    actingAs($user->fresh())->get(route('posts.create'))->assertOk();
});
