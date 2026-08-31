<?php

declare(strict_types=1);

/**
 * Admin removal of user avatars.
 */

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Media;
use App\Models\User;
use App\Services\ImageService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function avatarPngPath(): string
{
    $path = tempnam(sys_get_temp_dir(), 'orb').'.png';
    $im = imagecreatetruecolor(64, 64);
    imagefill($im, 0, 0, imagecolorallocate($im, 5, 10, 15));
    imagepng($im, $path);
    imagedestroy($im);

    return $path;
}

function userWithAvatar(string $username): User
{
    Storage::fake('local');
    $user = User::factory()->createOne(['username' => $username, 'email_verified_at' => now()]);
    $src = avatarPngPath();

    $path = app(ImageService::class)->uploadAvatar(
        new UploadedFile($src, 'avatar.png', 'image/png', null, true),
        $user->id,
    );
    @unlink($src);

    $user->update(['avatar_url' => $path]);

    return $user->fresh();
}

it('remove avatar action is hidden for a user with no avatar', function () {
    Filament::setCurrentPanel('admin');
    $admin = AdminUsers::admin('admin_no_avatar_target');
    $target = User::factory()->createOne(['username' => 'sem_avatar', 'avatar_url' => null, 'email_verified_at' => now()]);

    actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('removeAvatar', $target);
});

it('remove avatar action is visible for staff when the user has one', function () {
    Filament::setCurrentPanel('admin');
    $moderator = AdminUsers::moderator('mod_avatar_target');
    $target = userWithAvatar('com_avatar');

    actingAs($moderator);

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('removeAvatar', $target);
});

it('staff can remove a users avatar', function () {
    Filament::setCurrentPanel('admin');
    $moderator = AdminUsers::moderator('mod_remove_avatar');
    $target = userWithAvatar('vai_perder_avatar');

    $media = Media::where('media_type', Media::TYPE_AVATAR)
        ->where('path', ltrim(str_replace('/s/', '', (string) $target->avatar_url), '/'))
        ->firstOrFail();

    actingAs($moderator);

    Livewire::test(ListUsers::class)
        ->callTableAction('removeAvatar', $target);

    $target->refresh();
    expect($target->avatar_url)->toBeNull();

    $media->refresh();
    expect($media->deleted_at)->not->toBeNull()
        ->and($media->status)->toBe('deleted');
});
