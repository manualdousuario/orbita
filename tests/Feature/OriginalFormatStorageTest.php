<?php

declare(strict_types=1);

/**
 * Images are stored exactly as uploaded; conversion happens at display time.
 */

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Pipeline\Image\ImagePipeline;
use App\Pipeline\Image\ImageUploadContext;
use App\Pipeline\Image\ProcessImageStage;
use App\Pipeline\Image\StoreImageStage;
use App\Pipeline\Image\ValidateImageStage;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

uses(RefreshDatabase::class);

function runImagePipeline(string $tmpPath, int $userId): ImageUploadContext
{
    $disk = Storage::disk('local');

    $ctx = new ImageUploadContext(
        file: ['tmp_name' => $tmpPath, 'size' => filesize($tmpPath)],
        directory: 'posts',
        userId: $userId,
    );

    $manager = new ImageManager(new Driver);

    return (new ImagePipeline)
        ->pipe(new ValidateImageStage(new FinfoMimeTypeDetector, 10485760))
        ->pipe(new ProcessImageStage($manager))
        ->pipe(new StoreImageStage($disk))
        ->process($ctx);
}

function makeTransparentPng(int $w = 640, int $h = 480): string
{
    $path = tempnam(sys_get_temp_dir(), 'orb').'.png';
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagepng($im, $path);
    imagedestroy($im);

    return $path;
}

it('png is stored as png not converted to jpeg', function () {
    Storage::fake('local');
    $user = User::factory()->createOne(['username' => 'fotografo']);
    $src = makeTransparentPng();

    $ctx = runImagePipeline($src, $user->id);

    expect($ctx->result['success'])->toBeTrue();

    $media = Media::findOrFail($ctx->result['media_id']);
    expect($media->mime_type)->toBe('image/png')
        ->and($media->file_extension)->toBe('png');
    expect($media->path)->toEndWith('.png');

    unlink($src);
});

it('stored bytes are the original so file hash matches disk', function () {
    Storage::fake('local');
    $user = User::factory()->createOne(['username' => 'hasher']);
    $src = makeTransparentPng();
    $originalHash = hash_file('sha256', $src);

    $ctx = runImagePipeline($src, $user->id);
    $media = Media::findOrFail($ctx->result['media_id']);

    expect($media->file_hash)->toBe($originalHash);

    // file_hash must describe the stored bytes, not a re-encoded copy.
    $stored = Storage::disk('local')->get($media->path);
    expect(hash('sha256', $stored))->toBe($originalHash)
        ->and($media->file_size)->toBe(strlen($stored));

    unlink($src);
});

it('animated gif is stored intact and flagged as animated', function () {
    Storage::fake('local');
    $user = User::factory()->createOne(['username' => 'gifeiro']);

    $gif = new Imagick;
    foreach (['#ff0000', '#00ff00', '#0000ff'] as $color) {
        $frame = new Imagick;
        $frame->newImage(60, 60, new ImagickPixel($color));
        $frame->setImageFormat('gif');
        $gif->addImage($frame);
    }
    $gif->setImageFormat('gif');
    $src = tempnam(sys_get_temp_dir(), 'orb').'.gif';
    file_put_contents($src, $gif->getImagesBlob());

    $ctx = runImagePipeline($src, $user->id);
    $media = Media::findOrFail($ctx->result['media_id']);

    expect($media->file_extension)->toBe('gif')
        ->and($media->metadata['animated'])->toBeTrue('animated GIF must be flagged so display never converts it');

    // All three frames survived: we stored the original bytes.
    $tmp = tempnam(sys_get_temp_dir(), 'chk').'.gif';
    file_put_contents($tmp, Storage::disk('local')->get($media->path));
    $probe = new Imagick($tmp);
    $frames = $probe->getNumberImages();
    $probe->clear();
    $probe->destroy();
    @unlink($tmp);
    @unlink($src);

    expect($frames)->toBe(3);
});

/**
 * The stored path extension must match the encoded format, not a hardcoded .jpg.
 */
it('avatar path extension matches the encoded format', function () {
    Storage::fake('local');
    $user = User::factory()->createOne(['username' => 'avatarista']);
    $src = makeTransparentPng();

    $path = app(ImageService::class)->uploadAvatar(
        new UploadedFile($src, 'avatar.png', 'image/png', null, true),
        $user->id,
    );

    expect($path)->toEndWith('.png');

    $media = Media::where('media_type', Media::TYPE_AVATAR)->firstOrFail();
    expect($media->file_extension)->toBe('png');
    expect($media->path)->toEndWith('.png');
    expect($media->metadata)->toHaveKey('animated');

    @unlink($src);
});

it('static image is not flagged as animated', function () {
    Storage::fake('local');
    $user = User::factory()->createOne(['username' => 'estatico']);
    $src = makeTransparentPng();

    $ctx = runImagePipeline($src, $user->id);
    $media = Media::findOrFail($ctx->result['media_id']);

    expect($media->metadata['animated'])->toBeFalse();

    unlink($src);
});

it('original dimensions are preserved and recorded', function () {
    Storage::fake('local');
    $user = User::factory()->createOne(['username' => 'grandao']);

    // Wider than the legacy 1980px cap: it must NOT be downscaled on upload.
    $src = makeTransparentPng(2400, 1000);

    $ctx = runImagePipeline($src, $user->id);
    $media = Media::findOrFail($ctx->result['media_id']);

    expect($media->metadata['width'])->toBe(2400)
        ->and($media->metadata['height'])->toBe(1000);

    $stored = Storage::disk('local')->get($media->path);
    [$w, $h] = getimagesizefromstring($stored);
    expect($w)->toBe(2400)
        ->and($h)->toBe(1000);

    unlink($src);
});
