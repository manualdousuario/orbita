<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

use function Pest\Laravel\followingRedirects;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function sessionErrors(array $messages): ViewErrorBag
{
    return (new ViewErrorBag)->put('default', new MessageBag($messages));
}

it('marks the failing field invalid and points it at its inline error and hint', function () {
    view()->share('errors', sessionErrors([
        'password' => ['A senha deve ter pelo menos 10 caracteres.'],
    ]));

    $html = view('auth.register')->render();

    preg_match('/<input type="password" id="password"[^>]*>/s', $html, $input);

    expect($input[0])->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="password-error password-hint"');

    expect($html)->toContain('id="password-error"')
        ->toContain('id="password-hint"')
        ->toContain('role="alert"')
        ->toContain('Confira os campos marcados abaixo.');

    expect(substr_count($html, 'A senha deve ter pelo menos 10 caracteres.'))->toBe(1);
});

it('a valid field keeps only its hint', function () {
    $html = (string) get(route('register'))->assertOk()->getContent();

    preg_match('/<input type="text" id="username"[^>]*>/s', $html, $input);

    expect($input[0])->toContain('aria-describedby="username-hint"');
    expect($input[0])->not->toContain('aria-invalid');
    expect($html)->not->toContain('role="alert"');
});

it('errors that belong to no field stay in the summary', function () {
    view()->share('errors', sessionErrors([
        'cf-turnstile-response' => ['Confirme que você não é um robô.'],
    ]));

    $html = view('auth.login')->render();

    expect($html)->toContain('role="alert"')
        ->toContain('Confirme que você não é um robô.');
});

it('a real failed registration renders the inline error', function () {
    followingRedirects()
        ->from(route('register'))
        ->post(route('register'), [
            'email' => 'nova@example.com',
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])
        ->assertSee('id="password-error"', false)
        ->assertSee('aria-invalid="true"', false);
});
