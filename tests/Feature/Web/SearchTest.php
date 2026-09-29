<?php

declare(strict_types=1);

/**
 * Fulltext search behavior and its test isolation strategy.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

/*
| DatabaseTruncation, not RefreshDatabase: the FULLTEXT index only sees
| committed rows. This is why tests/Pest.php never applies RefreshDatabase
| suite-wide.
*/
uses(DatabaseTruncation::class);

/*
| Delete rows so committed data does not leak into the next class's refresh.
| Pest runs afterEach() before parent::tearDown() (Testable::tearDown), so this
| keeps the ordering the original tearDown depended on.
*/
afterEach(function () {
    foreach (['comments', 'posts', 'users'] as $table) {
        DB::table($table)->delete();
    }
});

it('search finds matching post by title via fulltext', function () {
    $author = User::factory()->createOne([
        'username' => 'buscador',
        'email_verified_at' => now(),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(1),
        'title' => 'Exoplanetas habitáveis na zona de Goldilocks',
        'slug' => 'exoplanetas-habitaveis',
        'content' => 'Conteúdo sobre mundos distantes.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(2),
        'title' => 'Assunto totalmente diferente',
        'slug' => 'assunto-diferente',
        'content' => 'Nada a ver.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $response = get('/search?q=Exoplanetas');

    $response->assertOk();
    $response->assertSee('Exoplanetas habitáveis na zona de Goldilocks');
    $response->assertDontSee('Assunto totalmente diferente');
});

it('search without query shows prompt', function () {
    get('/search')
        ->assertOk()
        ->assertSee('Digite algo para buscar');
});

it('search marks a post closed for comments', function () {
    $author = User::factory()->createOne([
        'username' => 'trancador',
        'email_verified_at' => now(),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(11),
        'title' => 'Supernovas trancadas para novas respostas',
        'slug' => 'supernovas-trancadas',
        'content' => 'Conteúdo sobre estrelas que explodiram.',
        'status' => 'published',
        'allow_comments' => false,
        'published_at' => now(),
    ]);

    get('/search?q=Supernovas')
        ->assertOk()
        ->assertSee('Supernovas trancadas para novas respostas')
        ->assertSee('Comentários fechados');
});

it('search leaves comment results unmarked even when the parent post is closed', function () {
    $author = User::factory()->createOne([
        'username' => 'comentador',
        'email_verified_at' => now(),
    ]);

    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(12),
        'title' => 'Quasares distantes',
        'slug' => 'quasares-distantes',
        'content' => 'Nada relacionado ao termo buscado.',
        'status' => 'published',
        'allow_comments' => false,
        'published_at' => now(),
    ]);

    DB::table('comments')->insert([
        'hashid' => HashId::encode(13),
        'user_id' => $author->id,
        'post_id' => $post->id,
        'content' => 'Observação sobre magnetares brilhantes.',
        'status' => 'visible',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    get('/search?q=magnetares&type=comments')
        ->assertOk()
        ->assertSee('Quasares distantes')
        ->assertDontSee('Comentários fechados');
});

it('search finds closed posts, which are most of the archive', function () {
    $author = User::factory()->createOne([
        'username' => 'arquivista',
        'email_verified_at' => now(),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(21),
        'title' => 'Pulsares antigos do acervo',
        'slug' => 'pulsares-antigos',
        'content' => 'Post que fechou por inatividade.',
        'status' => 'closed',
        'allow_comments' => false,
        'published_at' => now()->subYear(),
    ]);

    get('/search?q=Pulsares&type=posts')
        ->assertOk()
        ->assertSee('Pulsares antigos do acervo')
        ->assertSee('Comentários fechados');

    get('/search?q=Pulsares')
        ->assertOk()
        ->assertSee('Pulsares antigos do acervo');
});

it('search skips comments whose post is gone', function () {
    $author = User::factory()->createOne([
        'username' => 'apagador',
        'email_verified_at' => now(),
    ]);

    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(22),
        'title' => 'Post removido',
        'slug' => 'post-removido',
        'content' => 'Sem relação.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    DB::table('comments')->insert([
        'hashid' => HashId::encode(23),
        'user_id' => $author->id,
        'post_id' => $post->id,
        'content' => 'Comentário sobre nebulosas escondidas.',
        'status' => 'visible',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $post->delete();

    get('/search?q=nebulosas&type=comments')
        ->assertOk()
        ->assertDontSee('Post removido');
});

it('search rows show a readable excerpt with the term highlighted', function () {
    $author = User::factory()->createOne([
        'username' => 'trechista',
        'email_verified_at' => now(),
    ]);

    Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(41),
        'title' => 'Magnetosferas',
        'slug' => 'magnetosferas',
        'content' => 'Leia [o artigo sobre **auroras**](https://exemplo.com/a) completo.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    get('/search?q=auroras')
        ->assertOk()
        ->assertSee('o artigo sobre <mark>auroras</mark> completo.', false)
        ->assertDontSee('](https://exemplo.com/a)', false);
});
