<?php

declare(strict_types=1);

/**
 * Post page rendering and admin edit link visibility.
 */

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('post show returns 200 and renders title and a visible comment', function () {
    seed(ReactionTypeSeeder::class);

    $author = User::factory()->createOne([
        'username' => 'astronauta',
        'display_name' => 'Astronauta',
        'email_verified_at' => now(),
    ]);

    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(1),
        'title' => 'Buracos negros e horizontes de eventos',
        'slug' => 'buracos-negros-e-horizontes-de-eventos',
        'content' => 'Um texto sobre **buracos negros**.',
        'status' => 'published',
        'allow_comments' => true,
        'score' => 4,
        'comment_count' => 1,
        'published_at' => now()->subHour(),
    ]);

    Comment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'parent_id' => null,
        'hashid' => HashId::encode(1),
        'content' => 'Comentario super visivel sobre o post',
        'nesting_level' => 0,
        'status' => 'visible',
    ]);

    $response = get('/p/'.$post->hashid);

    $response->assertOk();
    $response->assertSee('Buracos negros e horizontes de eventos');
    $response->assertSee('Comentario super visivel sobre o post');
});

it('post show accepts the optional slug segment', function () {
    $author = User::factory()->createOne([
        'username' => 'carl',
        'email_verified_at' => now(),
    ]);

    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(2),
        'title' => 'A pálida bolinha azul',
        'slug' => 'a-palida-bolinha-azul',
        'content' => 'Conteúdo',
        'status' => 'published',
        'published_at' => now(),
    ]);

    get('/p/'.$post->hashid.'/'.$post->slug)
        ->assertOk()
        ->assertSee('A pálida bolinha azul');
});

it('missing post returns 404', function () {
    get('/p/does-not-exist')->assertNotFound();
});

it('staff sees the edit in admin link', function () {
    $admin = User::factory()->createOne([
        'username' => 'staff_admin',
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $post = Post::create([
        'user_id' => $admin->id,
        'hashid' => HashId::encode(3),
        'title' => 'Post visível para a staff',
        'slug' => 'post-visivel-para-a-staff',
        'content' => 'Conteúdo',
        'status' => 'published',
        'published_at' => now(),
    ]);

    actingAs($admin)
        ->get('/p/'.$post->hashid)
        ->assertOk()
        ->assertSee('/admin/posts/'.$post->id.'/edit');
});

it('regular user does not see the edit in admin link', function () {
    $user = User::factory()->createOne([
        'username' => 'regular_joe',
        'role' => 'user',
        'email_verified_at' => now(),
    ]);

    $post = Post::create([
        'user_id' => $user->id,
        'hashid' => HashId::encode(4),
        'title' => 'Post visível para todos',
        'slug' => 'post-visivel-para-todos',
        'content' => 'Conteúdo',
        'status' => 'published',
        'published_at' => now(),
    ]);

    actingAs($user)
        ->get('/p/'.$post->hashid)
        ->assertOk()
        ->assertDontSee('/admin/posts/'.$post->id.'/edit');
});
