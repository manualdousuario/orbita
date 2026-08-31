<?php

declare(strict_types=1);

/**
 * Tests the orbita maintenance commands against the SQLite test connection.
 */

namespace Tests\Feature\Console;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

function commandsUser(string $suffix = ''): User
{
    return User::create([
        'username' => 'author'.$suffix,
        'email' => "author{$suffix}@example.com",
        'password' => 'secret123',
        'display_name' => 'Author '.$suffix,
        'role' => 'user',
    ]);
}

it('registers all orbita commands', function () {
    $names = array_keys(Artisan::all());

    foreach ([
        'orbita:posts-close-inactive',
        'orbita:notifications-clean',
        'orbita:reconcile',
    ] as $command) {
        expect($names)->toContain($command);
    }
});

it('posts close inactive closes a stale post', function () {
    config(['orbita.posts.close_inactive_days' => 30]);

    $user = commandsUser();

    $stale = Post::create([
        'user_id' => $user->id,
        'hashid' => 'stale1',
        'title' => 'Stale',
        'slug' => 'stale',
        'content' => 'c',
        'status' => 'published',
        'comment_count' => 0,
        'published_at' => now()->subDays(40),
    ]);

    $fresh = Post::create([
        'user_id' => $user->id,
        'hashid' => 'fresh1',
        'title' => 'Fresh',
        'slug' => 'fresh',
        'content' => 'c',
        'status' => 'published',
        'comment_count' => 0,
        'published_at' => now()->subDay(),
    ]);

    $active = Post::create([
        'user_id' => $user->id,
        'hashid' => 'active1',
        'title' => 'Active',
        'slug' => 'active',
        'content' => 'c',
        'status' => 'published',
        'comment_count' => 1,
        'published_at' => now()->subDays(60),
    ]);
    $comment = Comment::create([
        'hashid' => 'cmt1',
        'user_id' => $user->id,
        'post_id' => $active->id,
        'content' => 'recent',
        'status' => 'visible',
    ]);
    $comment->forceFill(['created_at' => now()->subDay()])->save();

    artisan('orbita:posts-close-inactive')->assertSuccessful();

    expect($stale->fresh()?->status)->toBe('closed')
        ->and((bool) $stale->fresh()?->allow_comments)->toBeFalse()
        ->and($fresh->fresh()?->status)->toBe('published')
        ->and((bool) $fresh->fresh()?->allow_comments)->toBeTrue()
        ->and($active->fresh()?->status)->toBe('published')
        ->and((bool) $active->fresh()?->allow_comments)->toBeTrue();
});

/**
 * Zero days disables the auto-close entirely.
 */
it('posts close inactive does nothing when threshold is zero', function () {
    config(['orbita.posts.close_inactive_days' => 0]);

    $user = commandsUser();

    $stale = Post::create([
        'user_id' => $user->id,
        'hashid' => 'stale0',
        'title' => 'Stale but protected',
        'slug' => 'stale-protected',
        'content' => 'c',
        'status' => 'published',
        'comment_count' => 0,
        'published_at' => now()->subDays(400),
    ]);

    artisan('orbita:posts-close-inactive')->assertSuccessful();

    expect($stale->fresh()?->status)->toBe('published');
});
