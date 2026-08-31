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
