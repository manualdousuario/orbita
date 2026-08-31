<?php

declare(strict_types=1);

/**
 * RSS feed output: published posts only.
 */

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function publishedFeedPost(User $author, int $n, string $title): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode($n),
        'title' => $title,
        'slug' => Str::slug($title),
        'content' => 'Conteúdo sobre **astronomia** e o cosmos.',
        'status' => 'published',
        'published_at' => now()->subMinutes($n),
    ]);
}

it('feed returns rss with a published post title', function () {
    $author = User::factory()->createOne([
        'username' => 'sagan',
        'display_name' => 'Carl Sagan',
        'email_verified_at' => now(),
    ]);

    publishedFeedPost($author, 1, 'A pálida bolinha azul');

    $response = get('/feed');

    $response->assertOk();

    expect((string) $response->headers->get('Content-Type'))->toContain('application/rss+xml');

    $response->assertSee('A pálida bolinha azul', false);
    $response->assertSee('Carl Sagan', false);
});

it('draft posts are excluded from the feed', function () {
    $author = User::factory()->createOne(['email_verified_at' => now()]);

    publishedFeedPost($author, 1, 'Publicado e visível');

    Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(2),
        'title' => 'Rascunho invisível',
        'slug' => 'rascunho-invisivel',
        'content' => 'Ainda não publicado',
        'status' => 'draft',
    ]);

    $response = get('/feed');

    $response->assertOk();
    $response->assertSee('Publicado e visível', false);
    $response->assertDontSee('Rascunho invisível', false);
});
