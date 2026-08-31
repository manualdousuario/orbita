<?php

declare(strict_types=1);

/**
 * GravatarService fetches avatars server-side and stores them locally.
 */

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\GravatarService;
use App\Support\Gravatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function gravatarPng(): string
{
    $im = imagecreatetruecolor(64, 64);
    imagefill($im, 0, 0, imagecolorallocate($im, 10, 20, 30));
    ob_start();
    imagepng($im);
    imagedestroy($im);

    return (string) ob_get_clean();
}

it('downloads and stores a gravatar when the user has none', function () {
    Storage::fake('local');
    $user = User::factory()->createOne(['email' => 'gravatar-user@example.com', 'avatar_url' => null]);

    Http::fake([
        Gravatar::url($user->email).'*' => Http::response(gravatarPng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $result = app(GravatarService::class)->syncForUser($user);

    expect($result)->toBeTrue();

    $user->refresh();
    expect($user->avatar_url)->not->toBeNull();
    expect(Media::where('media_type', Media::TYPE_AVATAR)->count())->toBe(1);
});

it('does not overwrite an existing avatar', function () {
    $user = User::factory()->createOne(['avatar_url' => '/s/avatars/existing.jpg']);

    Http::fake();

    $result = app(GravatarService::class)->syncForUser($user);

    expect($result)->toBeFalse()
        ->and($user->fresh()?->avatar_url)->toBe('/s/avatars/existing.jpg');

    Http::assertNothingSent();
});

it('leaves the avatar unset when gravatar has none', function () {
    $user = User::factory()->createOne(['email' => 'no-gravatar@example.com', 'avatar_url' => null]);

    Http::fake([
        Gravatar::url($user->email).'*' => Http::response('', 404),
    ]);

    $result = app(GravatarService::class)->syncForUser($user);

    expect($result)->toBeFalse()
        ->and($user->fresh()?->avatar_url)->toBeNull();
});

it('the config toggle disables the lookup entirely', function () {
    config(['orbita.avatar_gravatar_enabled' => false]);
    $user = User::factory()->createOne(['avatar_url' => null]);

    Http::fake();

    expect(app(GravatarService::class)->syncForUser($user))->toBeFalse();

    Http::assertNothingSent();
});
