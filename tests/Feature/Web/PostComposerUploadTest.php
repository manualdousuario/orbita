<?php

declare(strict_types=1);

/**
 * Image uploads through the post composer.
 */

namespace Tests\Feature\Web;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

/**
 * Its own factory rather than PostComposerResilienceTest's: sharing a namespaced
 * function across files would only work by the order Pest happens to include them.
 */
function composerUploadUser(): User
{
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    actingAs($user);

    return $user;
}

it('uploaded image is stored and linked to the created post', function () {
    Storage::fake('local');
    composerUploadUser();

    Livewire::test('⚡post-composer')
        ->set('title', 'Post com uma imagem enviada')
        ->set('content', 'Um conteúdo válido em Markdown.')
        ->set('pendingImage', UploadedFile::fake()->image('foto.jpg', 800, 600))
        ->call('insertImage')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $post = Post::query()->latest('id')->firstOrFail();

    assertDatabaseCount('media', 1);
    expect($post->media()->count())->toBe(1);

    $media = Media::query()->firstOrFail();
    assertDatabaseHas('media_relationship', [
        'post_id' => $post->id,
        'media_id' => $media->id,
    ]);
});

it('inserting more than the limit is ignored', function () {
    Storage::fake('local');
    composerUploadUser();

    $limit = (int) config('orbita.limit_images_post', 5);

    $component = Livewire::test('⚡post-composer')
        ->set('title', 'Post com imagens demais')
        ->set('content', 'Conteúdo.');

    for ($i = 0; $i < $limit; $i++) {
        $component
            ->set('pendingImage', UploadedFile::fake()->image("foto{$i}.jpg"))
            ->call('insertImage')
            ->assertHasNoErrors();
    }

    // One beyond the limit: silently refused, no new media is stored.
    $component
        ->set('pendingImage', UploadedFile::fake()->image('foto_extra.jpg'))
        ->call('insertImage');

    assertDatabaseCount('media', $limit);
});

it('a non image file is rejected by the composer', function () {
    Storage::fake('local');
    composerUploadUser();

    Livewire::test('⚡post-composer')
        ->set('title', 'Post com arquivo inválido')
        ->set('content', 'Conteúdo.')
        ->set('pendingImage', UploadedFile::fake()->create('documento.pdf', 120, 'application/pdf'))
        ->call('insertImage');

    assertDatabaseCount('media', 0);
});

it('editing re renders with a working cancel link', function () {
    $user = composerUser();
    $post = Post::create([
        'user_id' => $user->id,
        'hashid' => HashId::encode(1),
        'title' => 'Post existente',
        'slug' => 'post-existente',
        'content' => 'Conteúdo original.',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    Livewire::test('⚡post-composer', ['post' => $post])
        ->set('title', 'Título atualizado')
        ->assertHasNoErrors()
        ->assertSee(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]), false);
});
