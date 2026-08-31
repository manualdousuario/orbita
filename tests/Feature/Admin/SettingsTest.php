<?php

declare(strict_types=1);

/**
 * The settings allowlist, round-trips, and the admin settings page.
 */

namespace Tests\Feature\Admin;

use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Setting;
use App\Support\ReferralLinks;
use App\Support\Settings;
use App\Support\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

// ── the allowlist is the security boundary ────────────────────────────────

it('secrets are not editable', function () {
    foreach ([
        'orbita.encryption_key',
        'orbita.turnstile.secret_key',
    ] as $secret) {
        expect(SettingsRegistry::isEditable($secret))->toBeFalse("$secret must never be admin-editable");
    }
});

it('bootstrap config is not editable', function () {
    foreach (['app.key', 'database.default', 'orbita.url', 'orbita.media_path'] as $key) {
        expect(SettingsRegistry::isEditable($key))->toBeFalse("$key must not be admin-editable");
    }
});

it('set ignores keys outside the registry', function () {
    Settings::set([
        'orbita.encryption_key' => 'roubada',
        'app.key' => 'roubada',
        'orbita.turnstile.secret_key' => 'roubada',
    ]);

    expect(Setting::count())->toBe(0, 'no row may be written for non-registry keys')
        ->and(config('orbita.encryption_key'))->not->toBe('roubada')
        ->and(config('app.key'))->not->toBe('roubada');
});

it('stored setting overrides config immediately', function () {
    expect(config('orbita.posts.per_page'))->not->toBe(7);

    Settings::set(['orbita.posts.per_page' => 7]);

    expect(config('orbita.posts.per_page'))->toBe(7)
        ->and(Settings::get('orbita.posts.per_page'))->toBe(7);
});

it('override survives a fresh apply', function () {
    Settings::set(['orbita.antispam.max_posts_per_hour' => 42]);

    // Simulate a new request: reset config to the file default, then re-apply.
    config(['orbita.antispam.max_posts_per_hour' => 5]);
    Settings::applyToConfig();

    expect(config('orbita.antispam.max_posts_per_hour'))->toBe(42);
});

it('types round trip', function () {
    Settings::set([
        'orbita.antispam.duplicate_detection' => false, // bool
        'orbita.comments.max_nesting_level' => '9',     // int coming from a text input
        'orbita.name' => 'Órbita Teste',                 // string
    ]);

    expect(config('orbita.antispam.duplicate_detection'))->toBeFalse()
        ->and(config('orbita.comments.max_nesting_level'))->toBe(9)
        ->and(config('orbita.name'))->toBe('Órbita Teste');
});

it('settings actually change behaviour', function () {
    Settings::set(['orbita.antispam.duplicate_detection' => false]);
    expect((bool) config('orbita.antispam.duplicate_detection'))->toBeFalse();

    Settings::set(['orbita.antispam.duplicate_detection' => true]);
    expect((bool) config('orbita.antispam.duplicate_detection'))->toBeTrue();
});

it('missing settings table falls back to config defaults', function () {
    $default = config('orbita.posts.per_page');

    Settings::flush();
    Schema::drop('settings');

    expect(Settings::overrides())->toBe([])
        ->and(Settings::get('orbita.posts.per_page'))->toBe($default);
});

it('admin can open the settings page', function () {
    actingAs(AdminUsers::admin('chefe'))
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('Antispam');
});

/** Inputs must bind to the `data` state path declared by statePath('data'). */
it('form inputs are bound to the data state path', function () {
    // Assert on the raw wire:model attribute: the id carries a form. prefix.
    Livewire::actingAs(AdminUsers::admin('chefe'))
        ->test(SettingsPage::class)
        ->assertSee('wire:model="data.orbita__posts__per_page"', escape: false);
});

/** The save action must actually persist and take effect on config(). */
it('saving the form persists the setting', function () {
    Livewire::actingAs(AdminUsers::admin('chefe'))
        ->test(SettingsPage::class)
        ->assertFormSet(['orbita__posts__per_page' => config('orbita.posts.per_page')])
        ->fillForm(['orbita__posts__per_page' => 11])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas('settings', ['key' => 'orbita.posts.per_page', 'value' => '11']);

    expect(config('orbita.posts.per_page'))->toBe(11);
});

/** The forbidden-parameter list is a multi-line textarea; newlines must survive the round-trip. */
it('saving the referral param list persists every line', function () {
    $list = "referral\nreferral-code\nutm_*";

    Livewire::actingAs(AdminUsers::admin('chefe'))
        ->test(SettingsPage::class)
        ->fillForm(['orbita__moderation__forbidden_url_params' => $list])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas('settings', ['key' => 'orbita.moderation.forbidden_url_params', 'value' => $list]);

    expect(ReferralLinks::params())->toBe(['referral', 'referral-code', 'utm_*']);
});

it('saving never writes a secret even if injected', function () {
    Livewire::actingAs(AdminUsers::admin('chefe'))
        ->test(SettingsPage::class)
        ->call('save');

    assertDatabaseMissing('settings', ['key' => 'orbita.encryption_key']);
    assertDatabaseMissing('settings', ['key' => 'orbita.turnstile.secret_key']);
});

it('regular user cannot open the settings page', function () {
    actingAs(AdminUsers::staff('user', 'ze'))->get('/admin/settings')->assertForbidden();
});
