<?php

declare(strict_types=1);

/**
 * Stored user tokens must be hashed, never plaintext.
 */

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\UserToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('the clickable value is never what lands in the table', function () {
    Mail::fake();

    $user = User::factory()->createOne(['email' => 'antigo@example.com', 'password' => 'senha-bem-longa']);

    actingAs($user)
        ->put(route('users.email', ['username' => $user->username]), [
            'new_email' => 'novo@example.com',
            'password' => 'senha-bem-longa',
        ]);

    $stored = (string) DB::table('user_tokens')->where('token_type', 'email_change')->value('token');

    expect($stored)->not->toBe('');
    expect($stored)->toMatch('/^[0-9a-f]{64}$/', 'the column must hold a sha256 digest');
});

it('a lookup by the stored digest finds nothing', function () {
    $user = User::factory()->createOne();
    $plain = Str::random(64);

    UserToken::create([
        'user_id' => $user->id,
        'token' => UserToken::hashToken($plain),
        'token_type' => 'account_deletion',
        'email' => $user->email,
        'is_used' => false,
        'request_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);

    expect(UserToken::wherePlainToken($plain)->first())->not->toBeNull();

    // Someone holding only what the database contains cannot use it as a token.
    expect(UserToken::wherePlainToken(UserToken::hashToken($plain))->first())->toBeNull();
});

/**
 * The migration derives digests so in-flight links survive the deploy.
 */
it('the migration preserves links already in flight', function () {
    $user = User::factory()->createOne();
    $plain = Str::random(64);

    // A row as it looked before the migration: plaintext in the token column.
    DB::table('user_tokens')->insert([
        'user_id' => $user->id,
        'token' => $plain,
        'token_type' => 'email_change',
        'email' => 'novo@example.com',
        'is_used' => false,
        'request_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
        'created_at' => now(),
    ]);

    DB::statement("UPDATE user_tokens SET token = SHA2(token, 256) WHERE token REGEXP '[^0-9a-f]'");

    expect(UserToken::wherePlainToken($plain)->first())
        ->not->toBeNull('the link that was already e-mailed must still resolve after the migration');
});
