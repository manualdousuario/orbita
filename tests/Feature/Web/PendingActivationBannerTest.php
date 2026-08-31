<?php

declare(strict_types=1);

/**
 * Banners and affordances shown to pending (unverified) users.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function pendingBannerPost(?User $author = null): Post
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

it('banner is rendered for a pending user', function () {
    $user = User::factory()->unverified()->createOne(['email' => 'pendente@example.com']);

    $response = actingAs($user)->get('/');

    $response->assertSee('Sua conta ainda não foi ativada', false);
    $response->assertSee('pendente@example.com', false);
    $response->assertSee(route('verification.send'), false);
});

it('banner confirms the resend', function () {
    $user = User::factory()->unverified()->createOne();

    $response = actingAs($user)
        ->withSession(['status' => 'verification-link-sent'])
        ->get('/');

    $response->assertSee('Enviamos um novo link de ativação', false);
    $response->assertDontSee('Sua conta ainda não foi ativada', false);
});

it('banner is absent for a verified user', function () {
    actingAs(User::factory()->createOne())
        ->get('/')
        ->assertDontSee('Sua conta ainda não foi ativada', false);
});

it('banner is absent for a guest', function () {
    get('/')->assertDontSee('Sua conta ainda não foi ativada', false);
});

it('comment form offers activation instead of the editor', function () {
    $post = pendingBannerPost();
    $user = User::factory()->unverified()->createOne();

    $response = actingAs($user)->get(route('posts.show', ['hashid' => $post->hashid]));

    $response->assertSee('Ative sua conta', false);
    $response->assertSee(route('verification.notice'), false);
    $response->assertDontSee('Escreva um comentário...', false);
});

it('verified user still sees the comment editor', function () {
    $post = pendingBannerPost();

    actingAs(User::factory()->createOne())
        ->get(route('posts.show', ['hashid' => $post->hashid]))
        ->assertSee('Escreva um comentário...', false);
});

it('reactions point a pending user at the activation screen', function () {
    $post = pendingBannerPost();
    $user = User::factory()->unverified()->createOne();

    actingAs($user)
        ->get(route('posts.show', ['hashid' => $post->hashid]))
        ->assertSee('Ative sua conta para reagir', false);
});

it('bookmark button points a pending user at the activation screen', function () {
    $post = pendingBannerPost();
    $user = User::factory()->unverified()->createOne();

    actingAs($user)
        ->get(route('posts.show', ['hashid' => $post->hashid]))
        ->assertSee('Ative sua conta para acompanhar posts', false);
});
