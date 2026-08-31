<?php

declare(strict_types=1);

/**
 * Nesting level enforcement on comment replies.
 */

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

function nestingUser(string $username): User
{
    return User::create([
        'username' => $username,
        'email' => $username.'@example.com',
        'password' => 'secret123',
        'display_name' => $username,
        'role' => 'user',
    ]);
}

function nestingPost(User $author): Post
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

it('reply at max nesting level is allowed', function () {
    config(['orbita.comments.max_nesting_level' => 2]);

    $user = nestingUser('nester');
    $post = nestingPost($user);
    $service = app(CommentService::class);

    $root = $service->createComment(['post_id' => $post->id, 'user_id' => $user->id, 'content' => 'root']);
    $lvl1 = $service->createComment(['post_id' => $post->id, 'user_id' => $user->id, 'parent_id' => $root->id, 'content' => 'l1']);
    $lvl2 = $service->createComment(['post_id' => $post->id, 'user_id' => $user->id, 'parent_id' => $lvl1->id, 'content' => 'l2']);

    expect($root->nesting_level)->toBe(0)
        ->and($lvl1->nesting_level)->toBe(1)
        ->and($lvl2->nesting_level)->toBe(2);
});

it('reply beyond max nesting level is rejected', function () {
    config(['orbita.comments.max_nesting_level' => 2]);

    $user = nestingUser('nester');
    $post = nestingPost($user);
    $service = app(CommentService::class);

    $root = $service->createComment(['post_id' => $post->id, 'user_id' => $user->id, 'content' => 'root']);
    $lvl1 = $service->createComment(['post_id' => $post->id, 'user_id' => $user->id, 'parent_id' => $root->id, 'content' => 'l1']);
    $lvl2 = $service->createComment(['post_id' => $post->id, 'user_id' => $user->id, 'parent_id' => $lvl1->id, 'content' => 'l2']);

    // A reply to the level-2 comment would be level 3 > max(2): rejected.
    $service->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'parent_id' => $lvl2->id,
        'content' => 'l3',
    ]);
})->throws(RuntimeException::class, 'Maximum nesting level exceeded');
