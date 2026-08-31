<?php

declare(strict_types=1);

/**
 * Regressions found by the third workflow-backed review round.
 */

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use App\Support\Exif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;

uses(RefreshDatabase::class);

function r3User(string $tag): User
{
    return User::factory()->createOne(['username' => 'r3_'.$tag]);
}

// EXIF: binary tags must not make a valid photo unstorable.

it('binary exif is dropped so metadata stays json encodable', function () {
    $raw = [
        'Model' => 'iPhone 15',
        'MakerNote' => "\xff\xd8\xff\xe1 binário \x80\x81",   // invalid UTF-8
        'Orientation' => 1,
        'COMPUTED' => ['ApertureFNumber' => 'f/1.8', 'Blob' => "\x80\x81\x82"],
        'Huge' => str_repeat('x', 4096),
    ];

    $clean = Exif::sanitize($raw);

    expect(json_encode($clean))->not->toBeFalse('the sanitized EXIF must be JSON-encodable');
    expect($clean['Model'])->toBe('iPhone 15')
        ->and($clean['Orientation'])->toBe(1);
    expect($clean)->not->toHaveKey('MakerNote');
    expect($clean)->not->toHaveKey('Huge');

    // Inside a nested section, a well-encoded scalar survives while a binary sibling does not.
    expect($clean['COMPUTED']['ApertureFNumber'])->toBe('f/1.8');
    expect($clean['COMPUTED'])->not->toHaveKey('Blob');
});

it('media row survives binary exif', function () {
    $user = r3User('exif');

    // What Media::create() would do with the raw value: throw. With the sanitized one: not.
    $media = Media::create([
        'file_hash' => str_repeat('e', 64),
        'file_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'file_extension' => 'jpg',
        'file_type' => 'image', 'media_type' => Media::TYPE_POST, 'path' => 'posts/a.jpg',
        'file_size' => 10, 'uploaded_by' => $user->id, 'status' => 'active',
        'metadata' => ['exif' => Exif::sanitize(['MakerNote' => "\xff\xfe binário"])],
    ]);

    expect($media->fresh())->not->toBeNull();
});

it('the author is still blocked past the edit window', function () {
    config(['orbita.posts.edit_time_limit' => 60]);

    $author = r3User('auth');
    $post = Post::create([
        'user_id' => $author->id, 'hashid' => 'r3auth', 'title' => 'T', 'slug' => 't',
        'content' => 'c', 'status' => 'published', 'published_at' => now(),
    ]);
    DB::table('posts')->where('id', $post->id)
        ->update(['created_at' => now()->subDay()]);

    actingAs($author);

    expect(app(PostService::class)->canEdit($post->fresh()))->toBeFalse();

    expect(fn () => app(PostService::class)->assertWithinEditWindow($post->fresh()))
        ->toThrow(ValidationException::class);
});

it('an edit cannot blank both url and content', function () {
    config(['orbita.posts.edit_time_limit' => 0]);

    $author = r3User('blank');
    $post = Post::create([
        'user_id' => $author->id, 'hashid' => 'r3blk', 'title' => 'T', 'slug' => 't',
        'content' => 'corpo', 'status' => 'published', 'published_at' => now(),
    ]);

    actingAs($author);

    from('/p/r3blk/edit')
        ->put('/p/r3blk', ['title' => 'Novo titulo', 'url' => '', 'content' => ''])
        ->assertSessionHasErrors('content');

    expect($post->fresh()?->content)->toBe('corpo');
});
