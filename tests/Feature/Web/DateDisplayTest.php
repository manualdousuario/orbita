<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function dateDisplayInstant(): Carbon
{
    return Carbon::create(2026, 9, 15, 10, 41);
}

function dateDisplayPost(): Post
{
    $author = User::factory()->createOne([
        'username' => 'cronista',
        'display_name' => 'Cronista',
        'email_verified_at' => now(),
    ]);

    return Post::factory()->createOne([
        'user_id' => $author->id,
        'title' => 'Datas reais em vez de tempo relativo',
        'published_at' => dateDisplayInstant(),
        'created_at' => dateDisplayInstant(),
    ]);
}

it('shows the real date on the post page instead of relative time', function () {
    seed(ReactionTypeSeeder::class);

    $post = dateDisplayPost();

    $response = get('/p/'.$post->hashid);

    $response->assertOk();
    $response->assertSee('15/09/2026 10:41');
    $response->assertDontSee('atrás');
    $response->assertDontSee('há 1 hora');
});

it('keeps the full date in the tooltip', function () {
    seed(ReactionTypeSeeder::class);

    $post = dateDisplayPost();

    get('/p/'.$post->hashid)
        ->assertOk()
        ->assertSee('title="15 de setembro de 2026 às 10:41"', false);
});

it('shows the real date on the home feed', function () {
    seed(ReactionTypeSeeder::class);

    dateDisplayPost();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('15/09/2026 10:41');
    $response->assertDontSee('atrás');
});

it('shows the real date for comments in the tree', function () {
    seed(ReactionTypeSeeder::class);

    $post = dateDisplayPost();

    Comment::create([
        'user_id' => $post->user_id,
        'post_id' => $post->id,
        'parent_id' => null,
        'hashid' => HashId::encode(1),
        'content' => 'Comentario com data real',
        'nesting_level' => 0,
        'status' => 'visible',
        'created_at' => dateDisplayInstant(),
    ]);

    $response = get('/p/'.$post->hashid);

    $response->assertOk();
    $response->assertSee('Comentario com data real');
    $response->assertSee('15/09/2026 10:41');
    $response->assertDontSee('atrás');
});

it('follows APP_LOCALE without any translation string', function () {
    seed(ReactionTypeSeeder::class);

    $post = dateDisplayPost();

    app()->setLocale('en');

    $response = get('/p/'.$post->hashid);

    $response->assertOk();
    $response->assertSee('09/15/2026 10:41 AM');
    $response->assertSee('title="September 15, 2026 10:41 AM"', false);
    $response->assertDontSee('15/09/2026');
});
