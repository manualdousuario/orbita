<?php

declare(strict_types=1);

/**
 * Self-service account deletion: request -> emailed link -> confirmation page -> POST.
 */

namespace Tests\Feature;

use App\Actions\AnonymizeUser;
use App\Mail\AccountDeletionConfirmationMail;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Models\UserReaction;
use App\Models\UserToken;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function deletionUser(string $username = 'maria', string $email = 'maria@example.com'): User
{
    return User::factory()->createOne([
        'username' => $username,
        'email' => $email,
        'display_name' => 'Maria Silva',
        'password' => 'senha-secreta',
        'email_verified_at' => now(),
    ]);
}

/**
 * Creates a token row hashing the digest and hands back the plaintext in memory.
 *
 * @param  array<string, mixed>  $attrs
 */
function deletionToken(User $u, array $attrs = []): UserToken
{
    $plain = (string) ($attrs['token'] ?? bin2hex(random_bytes(16)));
    unset($attrs['token']);

    $token = UserToken::create(array_merge([
        'user_id' => $u->id,
        'token' => UserToken::hashToken($plain),
        'token_type' => 'account_deletion',
        'email' => $u->email,
        'is_used' => false,
        'request_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ], $attrs));

    $token->setAttribute('token', $plain);
    $token->syncOriginal();

    return $token;
}

it('request with correct password issues a token and queues the email', function () {
    Mail::fake();
    $u = deletionUser();

    actingAs($u)
        ->post(route('users.delete-account', ['username' => $u->username]), ['password' => 'senha-secreta'])
        ->assertRedirect(route('users.edit', ['username' => $u->username]))
        ->assertSessionHas('success');

    $token = UserToken::where('user_id', $u->id)->where('token_type', 'account_deletion')->first();

    expect($token)->not->toBeNull()
        ->and((bool) $token?->is_used)->toBeFalse()
        ->and($token?->expires_at->between(now()->addHours(23), now()->addHours(25)))->toBeTrue();

    Mail::assertQueued(AccountDeletionConfirmationMail::class);

    expect($u->fresh()?->anonymized_at)->toBeNull();
});

it('request with wrong password is rejected and creates no token', function () {
    Mail::fake();
    $u = deletionUser();

    actingAs($u)
        ->post(route('users.delete-account', ['username' => $u->username]), ['password' => 'errada'])
        ->assertSessionHasErrors('password', null, 'deleteAccount');

    expect(UserToken::where('token_type', 'account_deletion')->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('a user cannot request deletion of another account', function () {
    $victim = deletionUser('vitima', 'vitima@example.com');
    $other = deletionUser('outro', 'outro@example.com');

    actingAs($other)
        ->post(route('users.delete-account', ['username' => $victim->username]), ['password' => 'senha-secreta'])
        ->assertForbidden();

    expect(UserToken::where('token_type', 'account_deletion')->count())->toBe(0);
});

/**
 * Opening the link only renders the confirmation page, without consuming the token.
 */
it('opening the link only renders the confirmation page', function () {
    $u = deletionUser();
    $t = deletionToken($u);

    get(route('users.delete-account.confirm', $t->token))
        ->assertOk()
        ->assertSee('Confirmar exclusão da conta');

    expect($u->fresh()?->anonymized_at)->toBeNull()
        ->and((bool) $t->fresh()?->is_used)->toBeFalse();
});

it('confirming anonymizes the account and keeps the content', function () {
    seed(ReactionTypeSeeder::class);

    $u = deletionUser();
    $t = deletionToken($u);

    $post = Post::create([
        'user_id' => $u->id, 'hashid' => 'del1', 'title' => 'Meu post', 'slug' => 'meu-post',
        'content' => 'corpo', 'status' => 'published', 'allow_comments' => true, 'published_at' => now(),
    ]);
    $comment = Comment::create([
        'user_id' => $u->id, 'post_id' => $post->id, 'hashid' => 'delc1',
        'content' => 'meu comentário', 'status' => 'visible', 'nesting_level' => 0,
    ]);

    Bookmark::create(['user_id' => $u->id, 'post_id' => $post->id]);
    Notification::create([
        'user_id' => $u->id, 'type' => 'comment', 'title' => 'Oi', 'message' => 'msg', 'is_read' => false,
    ]);
    $reaction = UserReaction::create([
        'user_id' => $u->id,
        'reaction_type_id' => ReactionType::query()->firstOrFail()->id,
        'reactable_type' => 'post',
        'reactable_id' => $post->id,
    ]);

    post(route('users.delete-account.execute', $t->token))
        ->assertRedirect(route('login'))
        ->assertSessionHas('success');

    $u->refresh();

    expect($u->display_name)->toBe(AnonymizeUser::DISPLAY_NAME)
        ->and($u->username)->toBe('anonymized-'.$u->id)
        ->and($u->email)->toBe('anonymized-'.$u->id.'@orbita.invalid')
        ->and($u->password)->toBeNull()
        ->and($u->name)->toBeNull()
        ->and($u->email_verified_at)->toBeNull()
        ->and($u->anonymized_at)->not->toBeNull()
        // NOT soft-deleted: the row has to stay visible for the author byline to render.
        ->and($u->deleted_at)->toBeNull();

    // Content survives, still attributed to the same (now nameless) row.
    assertDatabaseHas('posts', ['id' => $post->id, 'user_id' => $u->id, 'deleted_at' => null]);
    assertDatabaseHas('comments', ['id' => $comment->id, 'user_id' => $u->id, 'deleted_at' => null]);

    assertDatabaseMissing('bookmarks', ['user_id' => $u->id]);
    assertDatabaseMissing('notifications', ['user_id' => $u->id]);

    // Reactions kept: deleting them would re-rank other people's posts.
    assertDatabaseHas('user_reactions', ['id' => $reaction->id, 'user_id' => $u->id]);

    // Token kept (so a second click reads "já foi utilizado") but scrubbed.
    $t->refresh();
    expect((bool) $t->is_used)->toBeTrue()
        ->and($t->used_at)->not->toBeNull()
        ->and($t->email)->toBe('anonymized-'.$u->id.'@orbita.invalid');
});

it('confirming logs the owner out', function () {
    $u = deletionUser();
    $t = deletionToken($u);

    actingAs($u)->post(route('users.delete-account.execute', $t->token));

    assertGuest();
});

it('confirming does not log out a different signed in user', function () {
    $target = deletionUser('alvo', 'alvo@example.com');
    $other = deletionUser('espectador', 'espectador@example.com');
    $t = deletionToken($target);

    actingAs($other)->post(route('users.delete-account.execute', $t->token));

    assertAuthenticatedAs($other->fresh());

    expect($target->fresh()?->anonymized_at)->not->toBeNull();
});

it('expired token is rejected and the account survives', function () {
    $u = deletionUser();
    $t = deletionToken($u, ['expires_at' => now()->subMinute()]);

    post(route('users.delete-account.execute', $t->token))
        ->assertSessionHas('error', 'Este link expirou.');

    expect($u->fresh()?->anonymized_at)->toBeNull();
});

it('used token is rejected for deletion', function () {
    $u = deletionUser();
    $t = deletionToken($u, ['is_used' => true, 'used_at' => now()]);

    post(route('users.delete-account.execute', $t->token))
        ->assertSessionHas('error', 'Este link já foi utilizado.');

    expect($u->fresh()?->anonymized_at)->toBeNull();
});

it('unknown token is rejected for deletion', function () {
    post(route('users.delete-account.execute', 'nao-existe'))
        ->assertSessionHas('error', 'Link inválido.');
});

it('a new request invalidates the previous pending link', function () {
    Mail::fake();
    $u = deletionUser();
    $stale = deletionToken($u);

    actingAs($u)
        ->post(route('users.delete-account', ['username' => $u->username]), ['password' => 'senha-secreta']);

    expect((bool) $stale->fresh()?->is_used)
        ->toBeTrue('stale account_deletion token should be invalidated');
});

it('the last admin cannot delete their account', function () {
    Mail::fake();
    $admin = User::factory()->createOne([
        'username' => 'unico_admin',
        'email' => 'admin@example.com',
        'password' => 'senha-secreta',
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    actingAs($admin)
        ->post(route('users.delete-account', ['username' => $admin->username]), ['password' => 'senha-secreta'])
        ->assertSessionHas('error');

    expect(UserToken::where('token_type', 'account_deletion')->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('deletion mailable builds url from named route', function () {
    $mail = new AccountDeletionConfirmationMail('Maria', 'tok123');

    expect($mail->render())->toContain(route('users.delete-account.confirm', ['token' => 'tok123']));
});
