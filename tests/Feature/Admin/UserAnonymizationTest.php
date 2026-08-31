<?php

declare(strict_types=1);

/**
 * The admin "exclude account" anonymize action and its limits.
 */

namespace Tests\Feature\Admin;

use App\Actions\AnonymizeUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('admin anonymizes a user keeping their content and logging it', function () {
    Filament::setCurrentPanel('admin');

    $admin = AdminUsers::admin('admin_anon');
    $target = User::factory()->createOne([
        'username' => 'alvo_anon',
        'display_name' => 'Alvo Original',
        'email' => 'alvo@example.com',
        'email_verified_at' => now(),
    ]);

    $post = Post::create([
        'user_id' => $target->id, 'hashid' => 'adm1', 'title' => 'Post do alvo', 'slug' => 'post-do-alvo',
        'content' => 'corpo', 'status' => 'published', 'allow_comments' => true, 'published_at' => now(),
    ]);
    $comment = Comment::create([
        'user_id' => $target->id, 'post_id' => $post->id, 'hashid' => 'admc1',
        'content' => 'comentário do alvo', 'status' => 'visible', 'nesting_level' => 0,
    ]);

    actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->callAction('anonymize', ['reason' => 'Pedido de exclusão via suporte'])
        ->assertHasNoActionErrors();

    $target->refresh();

    expect($target->display_name)->toBe(AnonymizeUser::DISPLAY_NAME)
        ->and($target->username)->toBe('anonymized-'.$target->id)
        ->and($target->anonymized_at)->not->toBeNull()
        ->and($target->deleted_at)->toBeNull();

    assertDatabaseHas('posts', ['id' => $post->id, 'user_id' => $target->id, 'deleted_at' => null]);
    assertDatabaseHas('comments', ['id' => $comment->id, 'user_id' => $target->id, 'deleted_at' => null]);

    assertDatabaseHas('moderation', [
        'moderator_id' => $admin->id,
        'action' => 'remove',
        'target_type' => 'user',
        'target_id' => $target->id,
        'reason' => 'Pedido de exclusão via suporte',
    ]);
});

it('an admin cannot anonymize their own account', function () {
    Filament::setCurrentPanel('admin');

    $admin = AdminUsers::admin('admin_self');

    actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->id])
        ->assertActionHidden('anonymize');

    expect($admin->fresh()?->anonymized_at)->toBeNull();
});

it('the action is hidden for an already deleted account', function () {
    Filament::setCurrentPanel('admin');

    $admin = AdminUsers::admin('admin_twice');
    $target = User::factory()->createOne(['username' => 'ja_excluido', 'email_verified_at' => now()]);

    app(AnonymizeUser::class)->handle($target);

    actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->assertActionHidden('anonymize')
        ->assertFormFieldDisabled('role')
        ->assertFormFieldDisabled('is_banned');
});

it('an anonymized staff account loses panel access', function () {
    $moderator = AdminUsers::moderator('mod_excluido');

    app(AnonymizeUser::class)->handle($moderator);

    actingAs($moderator->fresh())
        ->get('/admin')
        ->assertForbidden();
});

it('anonymizing is idempotent', function () {
    $target = User::factory()->createOne(['username' => 'idempotente', 'email_verified_at' => now()]);

    $action = app(AnonymizeUser::class);
    $action->handle($target);
    $first = $target->fresh()?->anonymized_at;

    $action->handle($target->fresh());

    expect($target->fresh()?->anonymized_at)->toEqual($first)
        ->and($target->fresh()?->username)->toBe('anonymized-'.$target->id);
});
