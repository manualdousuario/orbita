<?php

declare(strict_types=1);

/**
 * Comment image uploads and the server-side gate in the comment form.
 */

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function commentImagesUser(): User
{
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    actingAs($user);

    return $user;
}

function commentImagesPost(User $author): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => 'p'.$author->id,
        'title' => 'T',
        'slug' => 't',
        'content' => 'c',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('uploading an image links media to the new comment when allowed', function () {
    Storage::fake('local');
    config(['orbita.comments.allow_images' => true]);
    $user = commentImagesUser();
    $post = commentImagesPost($user);

    Livewire::test('comment-form', ['postId' => $post->id])
        ->set('content', 'Comentário com imagem.')
        ->set('pendingImage', UploadedFile::fake()->image('a.jpg', 800, 600))
        ->call('insertImage')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $comment = Comment::query()->latest('id')->firstOrFail();

    assertDatabaseCount('media', 1);
    expect($comment->media()->count())->toBe(1);

    $media = Media::query()->firstOrFail();
    assertDatabaseHas('comment_media', [
        'comment_id' => $comment->id,
        'media_id' => $media->id,
    ]);
});

it('server side gate rejects images when setting is off', function () {
    Storage::fake('local');
    config(['orbita.comments.allow_images' => false]);
    $user = commentImagesUser();
    $post = commentImagesPost($user);

    Livewire::test('comment-form', ['postId' => $post->id])
        ->set('content', 'Comentário sem imagem permitida.')
        ->set('pendingImage', UploadedFile::fake()->image('a.jpg', 800, 600))
        ->call('insertImage')
        ->call('save')
        ->assertHasNoErrors();

    $comment = Comment::query()->latest('id')->firstOrFail();

    expect($comment->content)->toBe('Comentário sem imagem permitida.');

    assertDatabaseCount('media', 0);
    assertDatabaseCount('comment_media', 0);

    expect($comment->media()->count())->toBe(0);
});

it('inserting more than the comment limit is ignored', function () {
    Storage::fake('local');
    config(['orbita.comments.allow_images' => true]);
    $user = commentImagesUser();
    $post = commentImagesPost($user);

    $limit = (int) config('orbita.comments.max_images', 4);

    $component = Livewire::test('comment-form', ['postId' => $post->id])
        ->set('content', 'Comentário com imagens.');

    for ($i = 0; $i < $limit; $i++) {
        $component
            ->set('pendingImage', UploadedFile::fake()->image("foto{$i}.jpg"))
            ->call('insertImage');
    }

    // One beyond the limit: silently refused, no new media is stored.
    $component
        ->set('pendingImage', UploadedFile::fake()->image('extra.jpg'))
        ->call('insertImage');

    assertDatabaseCount('media', $limit);
});

it('a non image file is rejected', function () {
    Storage::fake('local');
    config(['orbita.comments.allow_images' => true]);
    $user = commentImagesUser();
    $post = commentImagesPost($user);

    Livewire::test('comment-form', ['postId' => $post->id])
        ->set('content', 'Comentário com arquivo inválido.')
        ->set('pendingImage', UploadedFile::fake()->create('documento.pdf', 120, 'application/pdf'))
        ->call('insertImage');

    assertDatabaseCount('media', 0);
});
