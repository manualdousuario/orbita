<?php

declare(strict_types=1);

/**
 * Tests upload limits on the way in and path traversal on the way out.
 */

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * A minimal PNG header claiming the given dimensions.
 */
function pngHeaderClaiming(int $width, int $height): string
{
    $ihdr = 'IHDR'
        .pack('N', $width)
        .pack('N', $height)
        .chr(8)   // bit depth
        .chr(2)   // colour type: truecolour
        .chr(0).chr(0).chr(0);

    return "\x89PNG\r\n\x1a\n"
        .pack('N', 13).$ihdr.pack('N', 0);
}

/**
 * @return array{name: string, tmp_name: string, size: int, type: string}
 */
function upload(string $bytes, string $name = 'bomb.png'): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'sec');
    file_put_contents($tmp, $bytes);

    return ['name' => $name, 'tmp_name' => $tmp, 'size' => filesize($tmp), 'type' => 'image/png'];
}

it('rejects a decompression bomb before anything decodes it', function () {
    $user = User::factory()->create();

    $upload = upload(pngHeaderClaiming(40000, 40000));

    // The bomb must be small enough to beat the byte limit.
    expect($upload['size'])->toBeLessThan(1024);

    $result = app(ImageService::class)->uploadImage($upload, 'posts', (int) $user->id);

    expect($result['success'])->toBeFalse()
        ->and((string) $result['error'])->toContain('MP limit');
});

it('has a configurable ceiling', function () {
    config(['orbita.images.max_megapixels' => 1]);

    $user = User::factory()->create();

    $result = app(ImageService::class)->uploadImage(
        upload(pngHeaderClaiming(2000, 2000)), // 4 MP
        'posts',
        (int) $user->id,
    );

    expect($result['success'])->toBeFalse()
        ->and((string) $result['error'])->toContain('MP limit');
});

it('still uploads an ordinary image', function () {
    $user = User::factory()->create();

    $image = imagecreatetruecolor(20, 20);
    ob_start();
    imagepng($image);
    imagedestroy($image);
    $bytes = (string) ob_get_clean();

    $result = app(ImageService::class)->uploadImage(upload($bytes, 'ok.png'), 'posts', (int) $user->id);

    expect($result['success'])->toBeTrue();
});

/**
 * Path-traversal shapes are answered with 404, not a driver 500.
 */
it('answers traversal shapes with 404', function (string $path) {
    get('/s/'.$path)->assertNotFound();
})->with([
    ['../.env'],
    ['posts/../../.env'],
    ['./posts/x.png'],
    ['posts//x.png'],
]);
