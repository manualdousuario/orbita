<?php

declare(strict_types=1);

/**
 * Inline images in comments, capped at a thumbnail and opened via PhotoSwipe.
 */

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Support\ImageUrl;
use App\Support\InlineImages;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

/**
 * Author and host post for the test currently running; beforeEach() reseeds both.
 */
function commentImgAuthor(?User $set = null): User
{
    static $author = null;

    if ($set instanceof User) {
        $author = $set;
    }

    return $author;
}

function commentImgPost(?Post $set = null): Post
{
    static $post = null;

    if ($set instanceof Post) {
        $post = $set;
    }

    return $post;
}

beforeEach(function () {
    seed(ReactionTypeSeeder::class);

    $author = User::factory()->createOne(['email_verified_at' => now()]);
    commentImgAuthor($author);

    commentImgPost(Post::create([
        'user_id' => $author->id,
        'hashid' => 'cimg1',
        'title' => 'Assunto',
        'slug' => 'assunto',
        'content' => 'corpo',
        'status' => 'published',
        'allow_comments' => true,
        'published_at' => now(),
    ]));
});

/**
 * @param  array<string, mixed>  $overrides
 */
function commentImgMedia(string $name, array $overrides = []): Media
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
        'uploaded_by' => commentImgAuthor()->id,
    ], $overrides));
}

function commentWithImage(string $content, ?Media $media = null): Comment
{
    $comment = Comment::create([
        'user_id' => commentImgAuthor()->id,
        'post_id' => commentImgPost()->id,
        'hashid' => 'cm'.substr(hash('crc32b', $content), 0, 6),
        'content' => $content,
        'status' => 'visible',
        'nesting_level' => 0,
    ]);

    if ($media !== null) {
        $comment->media()->attach($media->id, ['display_order' => 0]);
    }

    return $comment;
}

function commentThreadHtml(): string
{
    $post = commentImgPost();

    return (string) get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()
        ->getContent();
}

it('comment images are served at twice the display cap', function () {
    $media = commentImgMedia('foto-comentario');
    commentWithImage('![]('.ImageUrl::original($media->path).')', $media);

    $html = commentThreadHtml();

    $box = InlineImages::COMMENT_MAX * 2;
    $expected = ImageUrl::media($media, ['width' => $box, 'height' => $box, 'fit' => 'contain']);

    expect($html)->toContain('src="'.e($expected).'"')
        ->toContain('md-content--comment');
});

it('a comment image opens the untouched original in the lightbox', function () {
    $media = commentImgMedia('foto-original');
    $url = ImageUrl::original($media->path);
    commentWithImage('![]('.$url.')', $media);

    $html = commentThreadHtml();

    expect($html)->toContain('class="pswp-item"')
        ->toContain('data-pswp-width="1600" data-pswp-height="1200"')
        // The href is the plain original: with JS disabled the click still opens it in a new tab.
        ->toContain('href="'.e($url).'" target="_blank"');
});

/** Comment images are never wrapped in the NSFW/Spoiler blur. */
it('comment images are never blurred', function () {
    $media = commentImgMedia('foto-comentario');
    commentWithImage('![]('.ImageUrl::original($media->path).')', $media);

    $html = commentThreadHtml();

    expect($html)->not->toContain('sensitive-media');
    expect($html)->toContain('class="pswp-item"');
});

it('an image already wrapped in a link is left alone', function () {
    $media = commentImgMedia('foto-linkada');
    $url = ImageUrl::original($media->path);
    commentWithImage('[![]('.$url.')]('.$url.')', $media);

    $html = commentThreadHtml();

    expect($html)->toContain('src="'.$url.'"');
    expect($html)->not->toContain('pswp-item');
});

it('two images in one comment both become photoswipe items', function () {
    $first = commentImgMedia('foto-a');
    $second = commentImgMedia('foto-b');

    $comment = commentWithImage(sprintf(
        "![](%s)\n\n![](%s)",
        ImageUrl::original($first->path),
        ImageUrl::original($second->path),
    ), $first);
    $comment->media()->attach($second->id, ['display_order' => 1]);

    $html = commentThreadHtml();

    expect(substr_count($html, 'class="pswp-item"'))->toBe(2);
});
