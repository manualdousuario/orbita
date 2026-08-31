<?php

declare(strict_types=1);

/**
 * Home page and login page rendering.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function seedHomePosts(): void
{
    $author = User::factory()->createOne([
        'username' => 'astronauta',
        'display_name' => 'Astronauta',
        'email_verified_at' => now(),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => 'h1',
        'title' => 'Primeiro post do universo',
        'slug' => 'primeiro-post-do-universo',
        'content' => 'Conteúdo',
        'status' => 'published',
        'score' => 5,
        'comment_count' => 2,
        'published_at' => now()->subHour(),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => 'h2',
        'title' => 'Segundo post estelar',
        'slug' => 'segundo-post-estelar',
        'content' => 'Conteúdo',
        'url' => 'https://example.com/artigo',
        'status' => 'published',
        'score' => 3,
        'comment_count' => 0,
        'published_at' => now()->subHours(2),
    ]);
}

it('home page renders brand and home feed component', function () {
    seedHomePosts();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('Órbita');
    $response->assertSeeLivewire('home-feed');
});

it('home feed lists seeded posts', function () {
    seedHomePosts();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('Primeiro post do universo');
    $response->assertSee('Segundo post estelar');
});

/** Link posts show the host next to the title, pointing at the URL exactly as posted. */
it('home feed shows the post host linking to the untouched url', function () {
    seedHomePosts();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('href="https://example.com/artigo"', false);
    $response->assertSee('example.com');
});

it('login page renders login form', function () {
    $response = get('/login');

    $response->assertOk();
    $response->assertSee('Entrar');
    $response->assertSee('Senha');
    $response->assertSee('Esqueci a senha');
});
