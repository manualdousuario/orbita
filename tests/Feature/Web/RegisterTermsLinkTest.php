<?php

declare(strict_types=1);

/**
 * Terms & conditions pages are linked from the register form.
 */

namespace Tests\Feature\Web;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function termsPage(array $attributes = []): Page
{
    return Page::create(array_merge([
        'slug' => 'termos',
        'title' => 'Termos e Condições',
        'content' => 'Conteúdo dos termos.',
        'is_terms' => true,
        'is_active' => true,
    ], $attributes));
}

it('active terms page is linked on the register form', function () {
    $page = termsPage();

    $response = get(route('register'));

    $response->assertOk();
    $response->assertSee('Ao criar uma conta, você concorda com', false);
    $response->assertSee(route('pages.show', ['slug' => $page->slug]), false);
    $response->assertSee('Termos e Condições', false);
});

it('inactive terms page is not linked', function () {
    $page = termsPage(['is_active' => false]);

    $response = get(route('register'));

    $response->assertOk();
    $response->assertDontSee('Ao criar uma conta, você concorda com', false);
    $response->assertDontSee(route('pages.show', ['slug' => $page->slug]), false);
});

it('page without the flag is not linked', function () {
    $page = termsPage(['slug' => 'sobre', 'title' => 'Sobre', 'is_terms' => false]);

    $response = get(route('register'));

    $response->assertOk();
    $response->assertDontSee('Ao criar uma conta, você concorda com', false);
    $response->assertDontSee(route('pages.show', ['slug' => $page->slug]), false);
});

it('multiple terms pages are joined with a conjunction', function () {
    termsPage();
    termsPage(['slug' => 'privacidade', 'title' => 'Política de Privacidade']);

    $response = get(route('register'));

    $response->assertOk();
    // Ordered by title: "Política de Privacidade e Termos e Condições".
    $response->assertSee(route('pages.show', ['slug' => 'privacidade']), false);
    $response->assertSee(route('pages.show', ['slug' => 'termos']), false);
    $response->assertSee('</a> e <a', false);
});

it('register form renders without any terms page', function () {
    $response = get(route('register'));

    $response->assertOk();
    $response->assertDontSee('Ao criar uma conta, você concorda com', false);
    $response->assertSee('Criar conta', false);
});
