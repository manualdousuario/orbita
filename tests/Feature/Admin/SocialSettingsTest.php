<?php

declare(strict_types=1);

/**
 * The admin toggles enabling social providers and email validation.
 */

namespace Tests\Feature\Admin;

use App\Enums\SocialProvider;
use App\Filament\Pages\Settings as SettingsPage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function socialSettingsAdmin(): User
{
    return User::factory()->createOne([
        'role' => 'admin',
        'is_banned' => false,
        'email_verified_at' => now(),
    ]);
}

it('the settings page shows the social group', function () {
    actingAs(socialSettingsAdmin())
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('Login social');
});

it('saving the toggles persists and takes effect', function () {
    Livewire::actingAs(socialSettingsAdmin())
        ->test(SettingsPage::class)
        ->fillForm([
            'orbita__social__google__enabled' => true,
            'orbita__social__google__require_email_confirmation' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas('settings', ['key' => 'orbita.social.google.enabled', 'value' => '1']);

    expect(SocialProvider::Google->isEnabled())->toBeTrue()
        ->and(SocialProvider::Google->requiresEmailConfirmation())->toBeTrue();
});

it('a disabled provider renders no button on the login screen', function () {
    config([
        'services.google.client_id' => 'id',
        'services.google.client_secret' => 'secret',
    ]);
    Settings::set(['orbita.social.google.enabled' => false]);

    get(route('login'))->assertOk()->assertDontSee('Entrar com Google');
});

it('an enabled but unconfigured provider renders no button', function () {
    // Credentials and the toggle are independent; the button needs both.
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    Settings::set(['orbita.social.google.enabled' => true]);

    get(route('login'))->assertOk()->assertDontSee('Entrar com Google');
});

it('a fully configured provider renders its button', function () {
    config([
        'services.google.client_id' => 'id',
        'services.google.client_secret' => 'secret',
    ]);
    Settings::set(['orbita.social.google.enabled' => true]);

    get(route('login'))->assertOk()->assertSee('Entrar com Google');
    get(route('register'))->assertOk()->assertSee('Entrar com Google');
});
