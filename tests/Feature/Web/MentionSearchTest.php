<?php

declare(strict_types=1);

/**
 * Typeahead endpoint behind the editor's @mention autocomplete.
 */

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

/**
 * forceFill: `role` and `is_banned` are not fillable on User.
 *
 * @param  array<string, mixed>  $overrides
 */
function mentionUser(string $username, array $overrides = []): User
{
    $user = new User;

    $user->forceFill(array_merge([
        'username' => $username,
        'email' => $username.'@example.com',
        'password' => 'secret123',
        'display_name' => ucfirst($username),
        'role' => 'user',
    ], $overrides))->save();

    return $user;
}

function actingMentionUser(): User
{
    $user = mentionUser('quemdigita');
    actingAs($user);

    return $user;
}

it('guest is redirected to login', function () {
    get('/mention-search?q=mar')->assertRedirect(route('login'));
});

it('matches by username prefix', function () {
    actingMentionUser();
    mentionUser('maria_silva');
    mentionUser('joao');

    $response = get('/mention-search?q=mar');

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.username', 'maria_silva');
});

it('matches by partial display name', function () {
    actingMentionUser();
    mentionUser('msilva', ['display_name' => 'Maria Silva']);

    $response = get('/mention-search?q=ria%20sil');

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.username', 'msilva');
    $response->assertJsonPath('0.display_name', 'Maria Silva');
});

it('username matches rank before display name matches', function () {
    actingMentionUser();
    mentionUser('ana_banana', ['display_name' => 'Maria Souza']); // display-name match only
    mentionUser('maria_silva'); // username-prefix match

    $response = get('/mention-search?q=maria');

    $response->assertOk();
    $response->assertJsonPath('0.username', 'maria_silva');
    $response->assertJsonPath('1.username', 'ana_banana');
});

it('excludes banned and soft deleted users', function () {
    actingMentionUser();
    mentionUser('maria_banida', ['is_banned' => true]);
    mentionUser('maria_apagada')->delete();
    mentionUser('maria_ativa');

    $response = get('/mention-search?q=maria');

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.username', 'maria_ativa');
});

it('results are limited to eight', function () {
    actingMentionUser();
    for ($i = 1; $i <= 10; $i++) {
        mentionUser('maria'.$i);
    }

    get('/mention-search?q=maria')->assertOk()->assertJsonCount(8);
});

it('payload carries avatar with dicebear fallback', function () {
    actingMentionUser();
    mentionUser('maria_foto', ['avatar_url' => '/s/avatars/maria.png']);
    mentionUser('maria_sem');

    $response = get('/mention-search?q=maria');

    $response->assertOk();
    $response->assertJsonStructure([['username', 'display_name', 'avatar_url']]);
    $response->assertJsonPath('0.avatar_url', '/s/avatars/maria.png');

    // Fallback mirrors the `avatar` Blade component: DiceBear seeded by username.
    expect($response->json('1.avatar_url'))->toContain('dicebear')->toContain('maria_sem');
});

it('requires a query term', function () {
    actingMentionUser();

    // The editor's fetch sends Accept: application/json, so failures come back as 422.
    getJson('/mention-search')->assertUnprocessable()->assertJsonValidationErrors('q');
    getJson('/mention-search?q=')->assertUnprocessable()->assertJsonValidationErrors('q');
});
