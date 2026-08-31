<?php

declare(strict_types=1);

/**
 * Admin panel access and user-management actions.
 */

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('admin can access admin panel', function () {
    actingAs(AdminUsers::staff('admin', 'admin_user'))
        ->get('/admin')
        ->assertOk();
});

it('regular user is forbidden from admin panel', function () {
    actingAs(AdminUsers::staff('user', 'regular_user'))
        ->get('/admin')
        ->assertForbidden();
});

it('admin can load users resource list page', function () {
    actingAs(AdminUsers::staff('admin', 'admin_two'))
        ->get('/admin/users')
        ->assertOk();
});

/** The dashboard renders for an admin without a name column. */
it('dashboard renders for admin without name column', function () {
    $admin = User::factory()->createOne([
        'name' => null,
        'username' => 'orbita_admin',
        'display_name' => 'Órbita',
        'role' => 'admin',
        'is_banned' => false,
        'email_verified_at' => now(),
    ]);

    expect($admin->getFilamentName())->toBe('Órbita');

    actingAs($admin)
        ->get('/admin')
        ->assertOk();
});

it('admin can load posts and comments list pages', function () {
    $admin = AdminUsers::staff('admin', 'admin_lists');

    actingAs($admin)->get('/admin/posts')->assertOk();
    actingAs($admin)->get('/admin/comments')->assertOk();
    actingAs($admin)->get('/admin/queue')->assertOk();
});

it('moderator can access admin panel', function () {
    actingAs(AdminUsers::staff('moderator', 'mod_user'))
        ->get('/admin')
        ->assertOk();
});

it('moderator can open users list and edit pages', function () {
    $moderator = AdminUsers::staff('moderator', 'mod_users');
    $target = User::factory()->createOne([
        'username' => 'target_user',
        'email_verified_at' => now(),
    ]);

    actingAs($moderator)->get('/admin/users')->assertOk();
    actingAs($moderator)->get("/admin/users/{$target->id}/edit")->assertOk();
});

it('moderator is forbidden from settings and queue pages', function () {
    $moderator = AdminUsers::staff('moderator', 'mod_ops');

    actingAs($moderator)->get('/admin/settings')->assertForbidden();
    actingAs($moderator)->get('/admin/queue')->assertForbidden();
});

it('edit user page identifies which user is being edited', function () {
    $admin = AdminUsers::staff('admin', 'admin_edit');
    $target = User::factory()->createOne([
        'username' => 'editado',
        'email' => 'editado@example.com',
        'email_verified_at' => now(),
    ]);

    actingAs($admin)
        ->get("/admin/users/{$target->id}/edit")
        ->assertOk()
        ->assertSee('Editar usuário: editado')
        ->assertSee('editado@example.com');
});

it('moderator cannot ban anonymize or change role', function () {
    Filament::setCurrentPanel('admin');

    $moderator = AdminUsers::staff('moderator', 'mod_actions');
    $target = User::factory()->createOne([
        'username' => 'target_actions',
        'email_verified_at' => now(),
    ]);

    actingAs($moderator);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('ban', $target);

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->assertActionHidden('anonymize')
        ->assertFormFieldDisabled('role')
        ->assertFormFieldDisabled('is_banned');
});

it('admin can ban anonymize and change role', function () {
    Filament::setCurrentPanel('admin');

    $admin = AdminUsers::staff('admin', 'admin_actions');
    $target = User::factory()->createOne([
        'username' => 'target_admin',
        'email_verified_at' => now(),
    ]);

    actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('ban', $target);

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->assertActionVisible('anonymize')
        ->assertFormFieldEnabled('role')
        ->assertFormFieldEnabled('is_banned');
});

it('send password reset link from edit user page', function () {
    Notification::fake();
    Filament::setCurrentPanel('admin');

    $admin = AdminUsers::staff('admin', 'admin_reset');
    $target = User::factory()->createOne([
        'username' => 'target_reset',
        'email_verified_at' => now(),
    ]);

    actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->callAction('sendPasswordReset');

    Notification::assertSentTo($target, ResetPasswordNotification::class);
});
