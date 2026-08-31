<?php

declare(strict_types=1);

/**
 * Uploads do not deduplicate: identical bytes produce independent media rows.
 */

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\ImageService;
use App\Support\MediaAttacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertSoftDeleted;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/** Deterministic: two calls produce byte-identical files. */
function dupPngBytes(): string
{
    $image = imagecreatetruecolor(2, 2);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

/**
 * @return array{name: string, tmp_name: string, size: int, type: string}
 */
function dupPngUpload(string $name = 'a.png'): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'dup');
    file_put_contents($tmp, dupPngBytes());

    return ['name' => $name, 'tmp_name' => $tmp, 'size' => filesize($tmp), 'type' => 'image/png'];
}

it('identical uploads create separate media rows and files', function () {
    $user = User::factory()->createOne(['username' => 'dup_a']);
    $images = app(ImageService::class);

    $first = $images->uploadImage(dupPngUpload(), 'posts', (int) $user->id);
    $second = $images->uploadImage(dupPngUpload(), 'posts', (int) $user->id);

    expect($first['success'])->toBeTrue((string) ($first['error'] ?? ''))
        ->and($second['success'])->toBeTrue((string) ($second['error'] ?? ''));

    expect($second['media_id'])->not->toBe($first['media_id']);
    expect($second['path'])->not->toBe($first['path']);
    expect(Media::count())->toBe(2);

    foreach ([$first, $second] as $result) {
        expect(Storage::disk('local')->exists(ltrim((string) $result['path'], '/')))->toBeTrue();
    }
});

/** file_hash is still recorded -- it identifies content -- it is just no longer unique. */
it('file hash is still written and describes the stored bytes', function () {
    $user = User::factory()->createOne(['username' => 'dup_h']);
    $upload = dupPngUpload();
    $expected = hash_file('sha256', $upload['tmp_name']);

    $result = app(ImageService::class)->uploadImage($upload, 'posts', (int) $user->id);
    $media = Media::findOrFail($result['media_id']);

    expect($media->file_hash)->toBe($expected)
        ->and(hash('sha256', Storage::disk('local')->get($media->path)))->toBe($expected);
});

it('two rows may share a file hash', function () {
    $user = User::factory()->createOne(['username' => 'dup_s']);
    $images = app(ImageService::class);

    $images->uploadImage(dupPngUpload(), 'posts', (int) $user->id);
    $images->uploadImage(dupPngUpload(), 'posts', (int) $user->id);

    $hashes = Media::pluck('file_hash')->all();

    expect($hashes)->toHaveCount(2)
        ->and($hashes[1])->toBe($hashes[0], 'same bytes, same hash, distinct rows');
});

/** Both images attach: they are different media rows now. */
it('attaching two identical images attaches both', function () {
    $user = User::factory()->createOne(['username' => 'dup_att']);
    $post = Post::create([
        'user_id' => $user->id, 'hashid' => 'dupatt', 'title' => 'T', 'slug' => 't',
        'content' => 'c', 'status' => 'published', 'published_at' => now(),
    ]);

    $attached = app(MediaAttacher::class)->attach(
        $post,
        [dupPngUpload('one.png'), dupPngUpload('two.png')],
        (int) $user->id,
    );

    expect($attached)->toBe(2)
        ->and($post->media()->count())->toBe(2);
});

/** Re-saving the same avatar just stores it again; no unique-constraint blow-up. */
it('uploading the same avatar twice stores two rows', function () {
    $user = User::factory()->createOne(['username' => 'dup_av']);
    $images = app(ImageService::class);

    $first = $images->uploadAvatar(
        new UploadedFile(dupPngUpload()['tmp_name'], 'a.png', 'image/png', null, true),
        (int) $user->id,
    );
    $second = $images->uploadAvatar(
        new UploadedFile(dupPngUpload()['tmp_name'], 'a.png', 'image/png', null, true),
        (int) $user->id,
    );

    expect($second)->not->toBe($first);
    expect(Media::where('media_type', Media::TYPE_AVATAR)->count())->toBe(2);

    // The returned URL is /s/<path>; strip the prefix to check the file.
    $storagePath = preg_replace('#^/s/#', '', $second);
    expect(Storage::disk('local')->exists($storagePath))->toBeTrue();
});

/** A deleted image can simply be uploaded again: nothing owns its hash any more. */
it('a deleted image can be uploaded again', function () {
    $user = User::factory()->createOne(['username' => 'dup_del']);
    $images = app(ImageService::class);

    $first = $images->uploadImage(dupPngUpload(), 'posts', (int) $user->id);
    $images->deleteImage((string) $first['path'], (int) $first['media_id'], (int) $user->id);

    $second = $images->uploadImage(dupPngUpload(), 'posts', (int) $user->id);

    expect($second['success'])->toBeTrue((string) ($second['error'] ?? ''))
        ->and($second['media_id'])->not->toBe($first['media_id']);

    // The removal stands: the old row is still soft-deleted, not resurrected.
    assertSoftDeleted('media', ['id' => $first['media_id']]);
});
