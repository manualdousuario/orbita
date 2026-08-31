<?php

declare(strict_types=1);

/**
 * Resource-level authorization gates for the admin panel.
 */

namespace Tests\Feature\Admin;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\ReactionTypes\ReactionTypeResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

// -----------------------------------------------------------------
// Governance resources: administrators only, like Settings and Queue.
// -----------------------------------------------------------------

it('moderator cannot reach the pages resource', function () {
    actingAs(AdminUsers::staff('moderator', 'mod_pages'));

    expect(PageResource::canViewAny())->toBeFalse()
        ->and(PageResource::canCreate())->toBeFalse();

    get('/admin/pages')->assertForbidden();
});

it('moderator cannot edit or delete the terms of use', function () {
    $terms = Page::create([
        'title' => 'Termos e condições',
        'slug' => 'termos',
        'content' => 'Conteúdo.',
        'is_terms' => true,
        'is_active' => true,
    ]);

    actingAs(AdminUsers::staff('moderator', 'mod_terms'));

    expect(PageResource::canEdit($terms))->toBeFalse()
        ->and(PageResource::canDelete($terms))->toBeFalse();

    get("/admin/pages/{$terms->id}/edit")->assertForbidden();
});

it('admin retains full access to the pages resource', function () {
    actingAs(AdminUsers::staff('admin', 'admin_pages'));

    expect(PageResource::canViewAny())->toBeTrue()
        ->and(PageResource::canCreate())->toBeTrue();

    get('/admin/pages')->assertOk();
});

it('moderator cannot reach the reaction types resource', function () {
    actingAs(AdminUsers::staff('moderator', 'mod_reactions'));

    expect(ReactionTypeResource::canViewAny())->toBeFalse();

    get('/admin/reaction-types')->assertForbidden();
});

it('admin retains access to the reaction types resource', function () {
    actingAs(AdminUsers::staff('admin', 'admin_reactions'));

    expect(ReactionTypeResource::canViewAny())->toBeTrue();
});

// -----------------------------------------------------------------
// Users: moderators keep the list (they act on accounts) but not the
// address book.
// -----------------------------------------------------------------

it('moderator does not see user emails in the list', function () {
    User::factory()->createOne([
        'username' => 'privacy_target',
        'email' => 'segredo@example.test',
        'email_verified_at' => now(),
    ]);

    actingAs(AdminUsers::staff('moderator', 'mod_emails'))
        ->get('/admin/users')
        ->assertOk()
        ->assertDontSee('segredo@example.test');
});

it('admin still sees user emails in the list', function () {
    User::factory()->createOne([
        'username' => 'visible_target',
        'email' => 'visivel@example.test',
        'email_verified_at' => now(),
    ]);

    actingAs(AdminUsers::staff('admin', 'admin_emails'))
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('visivel@example.test');
});

it('user resource still denies creation for everyone', function () {
    actingAs(AdminUsers::staff('admin', 'admin_nocreate'));

    expect(UserResource::canCreate())->toBeFalse();
});

// -----------------------------------------------------------------
// Non-staff must not reach the panel at all.
// -----------------------------------------------------------------

it('plain user is denied the admin panel', function () {
    actingAs(AdminUsers::staff('user', 'plain_user'))->get('/admin')->assertForbidden();
});
