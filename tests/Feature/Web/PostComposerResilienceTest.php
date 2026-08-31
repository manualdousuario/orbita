<?php

declare(strict_types=1);

/**
 * Composer service failures degrade to inline errors without losing typed content.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Services\ImageService;
use App\Services\OembedService;
use App\Services\PostService;
use App\Support\HashId;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use RuntimeException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

uses(RefreshDatabase::class);

/**
 * Mockery rather than $this->createMock(): the PHPUnit helper lives on the test
 * case, which a Pest closure cannot reach for the analyser.
 */
function composerUser(): User
{
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    actingAs($user);

    return $user;
}

function composerPost(User $user): Post
{
    return Post::create([
        'user_id' => $user->id,
        'hashid' => HashId::encode(1),
        'title' => 'Post existente',
        'slug' => 'post-existente',
        'content' => 'Conteúdo original.',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);
}

it('a failing oembed provider does not break the url field', function () {
    composerUser();

    $oembed = Mockery::mock(OembedService::class);
    $oembed->shouldReceive('checkUrl')->andReturn(['provider' => 'youtube']);
    $oembed->shouldReceive('fetchOembed')->andThrow(new RuntimeException('provider timeout'));
    app()->instance(OembedService::class, $oembed);

    Livewire::test('⚡post-composer')
        ->set('title', 'Post com link')
        ->set('url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->assertHasNoErrors()
        ->assertSet('oembedHtml', null)
        ->assertSet('oembedType', null)
        // The preview is optional; the field stays filled and the post is publishable.
        ->assertSet('url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('a failing oembed provider does not break mounting the edit screen', function () {
    $user = composerUser();
    $post = composerPost($user);
    $post->update(['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

    $oembed = Mockery::mock(OembedService::class);
    $oembed->shouldReceive('checkUrl')->andThrow(new RuntimeException('provider down'));
    app()->instance(OembedService::class, $oembed);

    Livewire::test('⚡post-composer', ['post' => $post])
        ->assertOk()
        ->assertSet('title', 'Post existente')
        ->assertSet('oembedHtml', null);
});

it('a failing image upload reports an inline error instead of a 500', function () {
    Storage::fake('local');
    composerUser();

    $images = Mockery::mock(ImageService::class);
    $images->shouldReceive('uploadImage')->andThrow(new RuntimeException('storage unavailable'));
    app()->instance(ImageService::class, $images);

    Livewire::test('⚡post-composer')
        ->set('title', 'Post com upload quebrado')
        ->set('content', 'Conteúdo em progresso.')
        ->set('pendingImage', UploadedFile::fake()->image('foto.jpg', 800, 600))
        ->call('insertImage')
        ->assertHasErrors('content')
        ->assertReturned('')
        ->assertSet('content', 'Conteúdo em progresso.');

    assertDatabaseCount('media', 0);
});

it('an unexpected failure while creating keeps the typed content', function () {
    composerUser();

    $posts = Mockery::mock(PostService::class);
    $posts->shouldReceive('checkAntispam')->andReturn(['allowed' => true]);
    $posts->shouldReceive('createPost')->andThrow(new Exception('deadlock'));
    app()->instance(PostService::class, $posts);

    Livewire::test('⚡post-composer')
        ->set('title', 'Post que não salva')
        ->set('content', 'Um texto longo que não pode ser perdido.')
        ->call('save')
        ->assertHasErrors('title')
        ->assertNoRedirect()
        ->assertSet('title', 'Post que não salva')
        ->assertSet('content', 'Um texto longo que não pode ser perdido.');

    assertDatabaseCount('posts', 0);
});

it('an unexpected failure while updating keeps the typed content', function () {
    $user = composerUser();
    $post = composerPost($user);

    $posts = Mockery::mock(PostService::class);
    $posts->shouldReceive('updatePost')->andThrow(new Exception('deadlock'));
    app()->instance(PostService::class, $posts);

    Livewire::test('⚡post-composer', ['post' => $post])
        ->set('content', 'Edição que não pode ser perdida.')
        ->call('save')
        ->assertHasErrors('title')
        ->assertNoRedirect()
        ->assertSet('content', 'Edição que não pode ser perdida.');

    expect($post->fresh()?->content)->toBe('Conteúdo original.');
});
