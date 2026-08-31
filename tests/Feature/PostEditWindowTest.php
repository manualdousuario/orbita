<?php

declare(strict_types=1);

/**
 * The post edit window is enforced against created_at within the time limit.
 */

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function editWindowUser(): User
{
    return User::create([
        'username' => 'editor',
        'email' => 'editor@example.com',
        'password' => 'secret123',
        'display_name' => 'editor',
        'role' => 'user',
    ]);
}

function editWindowPost(User $author, DateTimeInterface $createdAt): Post
{
    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'pe1',
        'title' => 'T',
        'slug' => 't',
        'content' => 'c',
        'status' => 'published',
        'published_at' => $createdAt,
    ]);

    // created_at drives the edit window; set it explicitly.
    $post->forceFill(['created_at' => $createdAt])->save();

    return $post->refresh();
}

it('edit is allowed within the edit time limit', function () {
    config(['orbita.posts.edit_time_limit' => 900]);

    $user = editWindowUser();
    // Created 60s ago: well inside the 900s window.
    $post = editWindowPost($user, now()->subSeconds(60));

    app(PostService::class)->assertWithinEditWindow($post);
})->throwsNoExceptions();

it('edit is blocked after the edit time limit', function () {
    config(['orbita.posts.edit_time_limit' => 900]);

    $user = editWindowUser();
    // Created 1000s ago: past the 900s window.
    $post = editWindowPost($user, now()->subSeconds(1000));

    app(PostService::class)->assertWithinEditWindow($post);
})->throws(ValidationException::class);
