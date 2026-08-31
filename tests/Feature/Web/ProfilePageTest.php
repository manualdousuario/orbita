<?php

declare(strict_types=1);

/**
 * Profile page rendering and admin edit link visibility.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('profile page returns 200 and shows display name and a post', function () {
    $user = User::factory()->createOne([
        'username' => 'saganita',
        'display_name' => 'Saganita Estelar',
        'email_verified_at' => now(),
    ]);

    Post::create([
        'user_id' => $user->id,
        'hashid' => HashId::encode(1),
        'title' => 'Anéis de Saturno em detalhe',
        'slug' => 'aneis-de-saturno-em-detalhe',
        'content' => 'Observações recentes.',
        'status' => 'published',
        'score' => 3,
        'comment_count' => 0,
        'published_at' => now()->subHour(),
    ]);

    $response = get('/u/'.$user->username);

    $response->assertOk();
    $response->assertSee('Saganita Estelar');
    $response->assertSee('Anéis de Saturno em detalhe');
});

it('unknown username returns 404', function () {
    get('/u/ninguem')->assertNotFound();
});

it('does not show the edit in admin link on profiles, not even to staff', function () {
    $moderator = User::factory()->createOne([
        'username' => 'mod_profile',
        'role' => 'moderator',
        'email_verified_at' => now(),
    ]);
    $target = User::factory()->createOne([
        'username' => 'perfil_alvo',
        'email_verified_at' => now(),
    ]);

    actingAs($moderator)
        ->get('/u/'.$target->username)
        ->assertOk()
        ->assertDontSee('/admin/users/'.$target->id.'/edit');
});
