<?php

declare(strict_types=1);

/**
 * Inline Markdown images in posts and their PhotoSwipe markup.
 */

namespace Tests\Feature\Web;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use App\Support\ImageUrl;
use App\Support\InlineImages;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

/**
 * The author for the test currently running; beforeEach() reseeds it, so the
 * helpers below all attach to the same one within a test.
 */
function inlineAuthor(?User $set = null): User
{
    static $author = null;

    if ($set instanceof User) {
        $author = $set;
    }

    return $author;
}

beforeEach(function () {
    seed(ReactionTypeSeeder::class);
    inlineAuthor(User::factory()->createOne(['email_verified_at' => now()]));
});

/**
 * @param  array<string, mixed>  $overrides
 */
function inlineMedia(string $name, array $overrides = []): Media
{
    return Media::create(array_merge([
        'file_hash' => hash('sha256', $name),
        'file_name' => $name.'.jpg',
        'mime_type' => 'image/jpeg',
        'file_extension' => 'jpg',
        'media_type' => Media::TYPE_POST,
        'path' => 'posts/'.$name.'.jpg',
        'file_size' => 1024,
        'metadata' => ['width' => 1600, 'height' => 1200],
        'uploaded_by' => inlineAuthor()->id,
    ], $overrides));
}

function inlinePost(string $content): Post
{
    return Post::create([
        'user_id' => inlineAuthor()->id,
        'hashid' => HashId::encode(1),
        'title' => 'Fotos da lua',
        'slug' => 'fotos-da-lua',
        'content' => $content,
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('inline images in post content render as img tags', function () {
    $media = inlineMedia('foto');
    $url = ImageUrl::original($media->path);
    $post = inlinePost("Veja esta imagem:\n\n![]({$url})\n\nFim.");
    $post->media()->attach($media->id, ['display_order' => 0]);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->toContain('<img');
    expect($html)->not->toContain('embla__viewport');
    expect($html)->not->toContain('postGallery(');
});

it('inline post images are served at twice the display cap', function () {
    $media = inlineMedia('foto');
    $url = ImageUrl::original($media->path);
    $post = inlinePost("![]({$url})");
    $post->media()->attach($media->id, ['display_order' => 0]);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    $box = InlineImages::POST_MAX * 2;
    $expected = ImageUrl::media($media, ['width' => $box, 'height' => $box, 'fit' => 'contain']);

    expect($html)->toContain('src="'.e($expected).'"')
        ->toContain('md-content--post')
        ->toContain('class="pswp-item"')
        ->toContain('href="'.e($url).'"')
        ->toContain('data-pswp-width="1600" data-pswp-height="1200"');
});

/** Every image of the same body sits in one `.md-content`, which is what groups the gallery. */
it('several images in one post all become photoswipe items', function () {
    $first = inlineMedia('foto-1');
    $second = inlineMedia('foto-2');
    $post = inlinePost(sprintf(
        "![](%s)\n\n![](%s)",
        ImageUrl::original($first->path),
        ImageUrl::original($second->path),
    ));
    $post->media()->attach($first->id, ['display_order' => 0]);
    $post->media()->attach($second->id, ['display_order' => 1]);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect(substr_count($html, 'class="pswp-item"'))->toBe(2);
});

it('animated images keep their original url', function () {
    $media = inlineMedia('gif', [
        'file_name' => 'gif.gif',
        'file_extension' => 'gif',
        'mime_type' => 'image/gif',
        'path' => 'posts/gif.gif',
        'metadata' => ['width' => 400, 'height' => 300, 'animated' => true],
    ]);
    $url = ImageUrl::original($media->path);
    $post = inlinePost("![]({$url})");
    $post->media()->attach($media->id, ['display_order' => 0]);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->toContain('src="'.$url.'"');
    expect($html)->not->toContain('w='.(InlineImages::POST_MAX * 2));
});

it('an image already wrapped in a link is left alone', function () {
    $media = inlineMedia('foto');
    $url = ImageUrl::original($media->path);
    $post = inlinePost("[![]({$url})]({$url})");
    $post->media()->attach($media->id, ['display_order' => 0]);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->toContain('src="'.$url.'"');
    expect($html)->not->toContain('pswp-item');
});

/** Inline post images are never wrapped in the NSFW/Spoiler blur. */
it('inline images are never blurred', function () {
    $media = inlineMedia('foto-qualquer');
    $url = ImageUrl::original($media->path);
    $post = inlinePost("![]({$url})");
    $post->media()->attach($media->id, ['display_order' => 0]);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->not->toContain('sensitive-media');
});

it('the generated og image is not rendered in the body', function () {
    $real = inlineMedia('foto');
    $url = ImageUrl::original($real->path);
    $post = inlinePost("![]({$url})");
    $post->media()->attach($real->id, ['display_order' => 0]);
    // The OG banner carries a different media_type and must never surface in the body.
    $post->media()->attach(inlineMedia('og-banner', ['media_type' => Media::TYPE_OGIMAGE])->id);

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->not->toContain('posts/og-banner.jpg');
});

it('staff sees an admin edit shortcut on inline images', function () {
    $media = inlineMedia('foto');
    $url = ImageUrl::original($media->path);
    $post = inlinePost("![]({$url})");
    $post->media()->attach($media->id, ['display_order' => 0]);

    $staff = User::factory()->createOne(['email_verified_at' => now(), 'role' => 'moderator']);

    $html = (string) actingAs($staff)->get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->toContain('media-edit-btn')
        ->toContain('/admin/media/'.$media->id.'/edit');

    expect($html)->toMatch(
        '#<span class="md-img-wrap"><a[^>]*class="pswp-item"[^>]*><img[^>]*></a><a[^>]*class="media-edit-btn"#',
    );
});

it('regular users and guests do not see the admin edit shortcut', function () {
    $media = inlineMedia('foto');
    $url = ImageUrl::original($media->path);
    $post = inlinePost("![]({$url})");
    $post->media()->attach($media->id, ['display_order' => 0]);

    $reader = User::factory()->createOne(['email_verified_at' => now()]);

    $userHtml = (string) actingAs($reader)->get('/p/'.$post->hashid)->assertOk()->getContent();
    expect($userHtml)->not->toContain('media-edit-btn');

    $guestHtml = (string) get('/p/'.$post->hashid)->assertOk()->getContent();
    expect($guestHtml)->not->toContain('media-edit-btn');
});
