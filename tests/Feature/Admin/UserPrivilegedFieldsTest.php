<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Admin\Concerns\AdminTables;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('an admin saving the form persists role and ban', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_form_save'));

    $target = User::factory()->createOne([
        'username' => 'alvo_form',
        'role' => UserRole::User->value,
        'is_banned' => false,
        'email_verified_at' => now(),
    ]);

    Activity::query()->delete();

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->fillForm(['role' => UserRole::Moderator->value, 'is_banned' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();

    expect($target->role)->toBe(UserRole::Moderator)
        ->and($target->is_banned)->toBeTrue()
        ->and(Activity::where('log_name', 'user')->count())->toBe(1);
});

it('a ban through the form is logged for moderation like the row action', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_form_log'));

    $target = User::factory()->createOne([
        'username' => 'alvo_log',
        'is_banned' => false,
        'email_verified_at' => now(),
    ]);

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->fillForm(['is_banned' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas('moderation', [
        'action' => 'ban_user',
        'target_type' => 'user',
        'target_id' => $target->id,
    ]);
});

it('a moderator cannot change role or ban through the form', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::moderator('mod_form_save'));

    $target = User::factory()->createOne([
        'username' => 'alvo_mod',
        'role' => UserRole::User->value,
        'is_banned' => false,
        'notify_replies_email' => true,
        'email_verified_at' => now(),
    ]);

    Livewire::test(EditUser::class, ['record' => $target->id])
        ->fillForm([
            'role' => UserRole::Admin->value,
            'is_banned' => true,
            'notify_replies_email' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();

    expect($target->role)->toBe(UserRole::User)
        ->and($target->is_banned)->toBeFalse()
        ->and($target->notify_replies_email)->toBeFalse();
});

it('the listing paints the negative state red, not the healthy one', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_flags'));

    $active = User::factory()->createOne([
        'username' => 'ativo_flag',
        'is_banned' => false,
        'email_verified_at' => now(),
    ]);
    $banned = User::factory()->createOne([
        'username' => 'banido_flag',
        'is_banned' => true,
        'email_verified_at' => now(),
    ]);

    $component = Livewire::test(ListUsers::class)
        ->assertTableColumnStateSet('is_banned', true, $banned)
        ->assertTableColumnStateSet('is_banned', false, $active);

    $isBanned = AdminTables::iconColumn($component, 'is_banned');
    expect($isBanned->getColor(true))->toBe('danger')
        ->and($isBanned->getColor(false))->toBe('gray')
        ->and($isBanned->getIcon(true))->toBe(Heroicon::OutlinedNoSymbol);

    $anonymized = AdminTables::iconColumn($component, 'anonymized_at');
    expect($anonymized->getColor(true))->toBe('danger')
        ->and($anonymized->getColor(false))->toBe('gray');

    $anonymized->record(AdminTables::of($component)->getTableRecord((string) $active->id));
    $anonymized->clearCachedState();
    expect($anonymized->getState())->toBeFalse();
});
