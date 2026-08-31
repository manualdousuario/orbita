<?php

declare(strict_types=1);

/**
 * Search query rewriting neutralises BOOLEAN MODE operator syntax.
 */

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;

uses(RefreshDatabase::class);

function rewriteQuery(string $query): string
{
    $method = new ReflectionMethod(SearchService::class, 'booleanQuery');

    return $method->invoke(app(SearchService::class), $query);
}

dataset('operatorPayloads', [
    'aspa solta' => ['livro "'],
    'parentese solto' => ['teste ('],
    'parenteses invertidos' => [') teste ('],
    'mais e menos' => ['+teste -outro'],
    'arroba de proximidade' => ['contato@dominio.com'],
    'til' => ['~teste'],
    'asterisco solto' => ['*'],
    'operadores puros' => ['+-@~<>()"*'],
    'menor maior' => ['<teste> outro'],
    'pontuacao com acento' => ['pré-sal, e-mail; "aspas"'],
]);

// -----------------------------------------------------------------
// Rewriting semantics.
// -----------------------------------------------------------------

it('a hyphen becomes a space instead of a not operator', function () {
    // "pré-sal" used to reach the server as "pré AND NOT sal".
    expect(rewriteQuery('pré-sal'))->toBe('pré sal')
        ->and(rewriteQuery('e-mail'))->toBe('e mail');
});

it('proximity and grouping operators are neutralised', function () {
    expect(rewriteQuery('contato@dominio.com'))->toBe('contato dominio.com')
        ->and(rewriteQuery('(teste)'))->toBe('teste')
        ->and(rewriteQuery('+teste -outro'))->toBe('teste outro')
        ->and(rewriteQuery('"frase exata"'))->toBe('frase exata');
});

it('ordinary words and accents are left alone', function () {
    expect(rewriteQuery('bacia de Santos'))->toBe('bacia de Santos')
        ->and(rewriteQuery('exploração petróleo'))->toBe('exploração petróleo');
});

it('whitespace is collapsed and trimmed', function () {
    expect(rewriteQuery("  a  --  b \n"))->toBe('a b')
        ->and(rewriteQuery('+++---'))->toBe('')
        ->and(rewriteQuery('   '))->toBe('');
});

// -----------------------------------------------------------------
// Integration: the statement must reach the server without erroring.
// -----------------------------------------------------------------

it('operator characters never produce a sql error', function (string $payload) {
    $author = User::factory()->createOne();
    Post::create([
        'hashid' => 'sqop0001',
        'user_id' => $author->id,
        'title' => 'Post comum',
        'slug' => 'post-comum',
        'content' => 'Conteúdo comum de teste.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    // The assertion is that this does not throw (no SQLSTATE 1064 mid-keystroke).
    $results = app(SearchService::class)->search($payload);

    expect($results['posts'])->toBeArray()
        ->and($results['comments'])->toBeArray();
})->with('operatorPayloads');

it('paginated search also survives operator characters', function (string $payload) {
    $paginator = app(SearchService::class)->searchPostsPaginated($payload, [], 10, 1);

    expect($paginator->currentPage())->toBe(1);
})->with('operatorPayloads');
