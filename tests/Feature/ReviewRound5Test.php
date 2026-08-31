<?php

declare(strict_types=1);

/**
 * Regressions found by the fifth review round.
 */

namespace Tests\Feature;

use App\Exceptions\InvalidImageException;
use App\Models\User;
use App\Services\ImageService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;
use function Pest\Laravel\withoutExceptionHandling;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function r5PngBytes(): string
{
    $image = imagecreatetruecolor(2, 2);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

function r5PngFile(): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'r5');
    file_put_contents($tmp, r5PngBytes());

    return new UploadedFile($tmp, 'a.png', 'image/png', null, true);
}

// A database failure must not be rendered as a form validation error.

it('query exception is a runtime exception', function () {
    expect(is_subclass_of(QueryException::class, RuntimeException::class))->toBeTrue(
        'se isso mudar, o catch estreito no UserController deixa de ser necessário',
    );
});

it('an invalid avatar becomes a form error', function () {
    $user = User::factory()->createOne(['username' => 'r5_bad']);

    $notAnImage = tempnam(sys_get_temp_dir(), 'r5');
    file_put_contents($notAnImage, 'isto nao e uma imagem');

    app(ImageService::class)->uploadAvatar(
        new UploadedFile($notAnImage, 'a.png', 'image/png', null, true),
        (int) $user->id,
    );
})->throws(InvalidImageException::class);

it('a database failure during avatar upload is not swallowed', function () {
    $user = User::factory()->createOne(['username' => 'r5_db']);

    mock(ImageService::class, function (MockInterface $mock) {
        $mock->shouldReceive('uploadAvatar')->andThrow(
            new QueryException('mysql', 'insert into media', [], new Exception('deadlock')),
        );
    });

    actingAs($user);

    // The DB error must surface, not become "o avatar é inválido".
    withoutExceptionHandling()
        ->put('/u/'.$user->username, [
            'display_name' => 'Nome',
            'avatar' => r5PngFile(),
        ]);
})->throws(QueryException::class);
