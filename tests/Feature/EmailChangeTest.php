<?php

declare(strict_types=1);

/**
 * Email change via emailed verification token.
 */

namespace Tests\Feature;

use App\Mail\EmailChangeVerificationMail;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\followingRedirects;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function emailChangeUser(string $username, string $email): User
{
    return User::factory()->createOne([
        'username' => $username,
        'email' => $email,
        'email_verified_at' => now(),
    ]);
}

/**
 * Creates a token row hashing the digest and hands back the plaintext in memory.
 *
 * @param  array<string, mixed>  $attrs
 */
function emailChangeToken(User $u, string $newEmail, array $attrs = []): UserToken
{
    $plain = (string) ($attrs['token'] ?? bin2hex(random_bytes(16)));
    unset($attrs['token']);

    $token = UserToken::create(array_merge([
        'user_id' => $u->id,
        'token' => UserToken::hashToken($plain),
        'token_type' => 'email_change',
        'email' => $newEmail,
        'is_used' => false,
        'request_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ], $attrs));

    $token->setAttribute('token', $plain);
    $token->syncOriginal();

    return $token;
}

it('valid token changes email and consumes token', function () {
    $u = emailChangeUser('maria', 'antigo@example.com');
    $t = emailChangeToken($u, 'novo@example.com');

    get(route('users.email.confirm', $t->token))
        ->assertRedirect()
        ->assertSessionHas('success');

    $u->refresh();
    $t->refresh();

    expect($u->email)->toBe('novo@example.com')
        ->and($u->email_verified_at)->not->toBeNull()
        ->and((bool) $t->is_used)->toBeTrue()
        ->and($t->used_at)->not->toBeNull();
});

it('expired token is rejected and email unchanged', function () {
    $u = emailChangeUser('joao', 'antigo@example.com');
    $t = emailChangeToken($u, 'novo@example.com', ['expires_at' => now()->subMinute()]);

    get(route('users.email.confirm', $t->token))
        ->assertSessionHas('error', 'Este link expirou.');

    expect($u->fresh()?->email)->toBe('antigo@example.com')
        ->and((bool) $t->fresh()?->is_used)->toBeFalse();
});

it('used token is rejected', function () {
    $u = emailChangeUser('ana', 'antigo@example.com');
    $t = emailChangeToken($u, 'novo@example.com', ['is_used' => true, 'used_at' => now()]);

    get(route('users.email.confirm', $t->token))
        ->assertSessionHas('error', 'Este link já foi utilizado.');

    expect($u->fresh()?->email)->toBe('antigo@example.com');
});

it('unknown token is rejected', function () {
    get(route('users.email.confirm', 'nao-existe'))
        ->assertSessionHas('error', 'Link inválido.');
});

it('email taken by another user is rejected', function () {
    $u = emailChangeUser('carlos', 'antigo@example.com');
    emailChangeUser('ocupante', 'novo@example.com');
    $t = emailChangeToken($u, 'novo@example.com');

    get(route('users.email.confirm', $t->token))
        ->assertSessionHas('error', 'Este email já está em uso.');

    expect($u->fresh()?->email)->toBe('antigo@example.com')
        ->and((bool) $t->fresh()?->is_used)->toBeFalse();
});

it('other pending tokens are invalidated on success', function () {
    $u = emailChangeUser('bea', 'antigo@example.com');
    $stale = emailChangeToken($u, 'outro@example.com');
    $good = emailChangeToken($u, 'novo@example.com');

    get(route('users.email.confirm', $good->token))->assertSessionHas('success');

    expect($u->fresh()?->email)->toBe('novo@example.com')
        ->and((bool) $stale->fresh()?->is_used)->toBeTrue('stale email_change token should be invalidated');
});

it('guest sees the error rendered on the login page', function () {
    $u = emailChangeUser('leo', 'antigo@example.com');
    $t = emailChangeToken($u, 'novo@example.com', ['expires_at' => now()->subMinute()]);

    followingRedirects()
        ->get(route('users.email.confirm', $t->token))
        ->assertOk()
        ->assertSee('Este link expirou.');
});

it('mailable builds url from named route', function () {
    $mail = new EmailChangeVerificationMail('Maria', 'novo@example.com', 'tok123');

    expect($mail->render())->toContain(route('users.email.confirm', ['token' => 'tok123']));
});
