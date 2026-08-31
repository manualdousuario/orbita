<?php

declare(strict_types=1);

/**
 * Tests the shared model factories and their unique hashids.
 */

namespace Tests\Feature\Database;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('posts can be made repeatedly without colliding', function () {
    $posts = Post::factory()->count(5)->create();

    expect($posts->pluck('hashid')->unique())->toHaveCount(5)
        ->and($posts->pluck('slug')->unique())->toHaveCount(5);
});

it('comments can be made repeatedly without colliding', function () {
    $comments = Comment::factory()->count(5)->create();

    expect($comments->pluck('hashid')->unique())->toHaveCount(5);
});

it('a post factory produces something the site would actually show', function () {
    $post = Post::factory()->createOne();

    get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()
        ->assertSee($post->title);
});

it('the link state makes a body less post', function () {
    $post = Post::factory()->link('https://example.com/artigo')->createOne();

    expect((string) $post->content)->toBe('')
        ->and($post->url)->toBe('https://example.com/artigo');
});

it('the closed state is readable but locked', function () {
    $post = Post::factory()->closed()->createOne();

    expect((bool) $post->allow_comments)->toBeFalse();

    get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))->assertOk();
});

/**
 * A reply agrees with its parent on post and nesting depth.
 */
it('reply to derives post and depth from the parent', function () {
    $root = Comment::factory()->createOne();
    $reply = Comment::factory()->replyTo($root)->createOne();

    expect((int) $reply->post_id)->toBe((int) $root->post_id)
        ->and((int) $reply->parent_id)->toBe((int) $root->id)
        ->and((int) $reply->nesting_level)->toBe(1);
});
