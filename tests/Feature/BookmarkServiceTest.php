<?php

declare(strict_types=1);

/**
 * Bookmark toggling, uniqueness, and listing behaviour.
 */

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Post;
use App\Models\User;
use App\Services\BookmarkService;
use App\Support\HashId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseCount;

uses(RefreshDatabase::class);

function bookmarkPost(User $author, string $suffix = 'a'): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(crc32($suffix)),
        'title' => 'Post '.$suffix,
        'slug' => 'post-'.$suffix,
        'content' => 'corpo',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('toggle saves then unsaves', function () {
    $user = User::factory()->createOne();
    $post = bookmarkPost($user);
    $service = app(BookmarkService::class);

    expect($service->toggle($user->id, $post->id))->toBeTrue()
        ->and($service->isBookmarked($user->id, $post->id))->toBeTrue();
    assertDatabaseCount('bookmarks', 1);

    expect($service->toggle($user->id, $post->id))->toBeFalse()
        ->and($service->isBookmarked($user->id, $post->id))->toBeFalse();
    assertDatabaseCount('bookmarks', 0);
});

/**
 * The unique index enforces one save per (user, post) pair.
 */
it('the unique index rejects a second row for the same pair', function () {
    $user = User::factory()->createOne();
    $post = bookmarkPost($user);

    Bookmark::create(['user_id' => $user->id, 'post_id' => $post->id]);
    Bookmark::create(['user_id' => $user->id, 'post_id' => $post->id]);
})->throws(UniqueConstraintViolationException::class);

/** Losing the insert race still leaves the caller in the state they asked for: saved. */
it('toggle reports saved when the row appears underneath it', function () {
    $user = User::factory()->createOne();
    $post = bookmarkPost($user);

    // Hook the SELECT to sneak the row in between toggle()'s read and insert.
    $raced = false;
    DB::listen(function ($query) use ($user, $post, &$raced) {
        if ($raced || ! str_contains($query->sql, 'select * from `bookmarks`')) {
            return;
        }
        $raced = true;
        DB::table('bookmarks')->insert([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    expect(app(BookmarkService::class)->toggle($user->id, $post->id))->toBeTrue();
    expect($raced)->toBeTrue('the racing insert never fired, so the catch block was not exercised');
    assertDatabaseCount('bookmarks', 1);
});

it('bookmarked post ids is one query for many posts', function () {
    $user = User::factory()->createOne();
    $posts = collect(['a', 'b', 'c', 'd', 'e'])->map(fn ($s) => bookmarkPost($user, $s));
    $service = app(BookmarkService::class);

    $service->toggle($user->id, $posts[0]->id);
    $service->toggle($user->id, $posts[3]->id);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $saved = $service->bookmarkedPostIds($user->id, $posts->pluck('id')->all());

    expect(DB::getQueryLog())->toHaveCount(1, 'bookmarkedPostIds must not scale with the number of posts');
    DB::disableQueryLog();

    expect($saved)->toBe([$posts[0]->id => true, $posts[3]->id => true]);
});

it('bookmarked post ids short circuits for guests and empty input', function () {
    $service = app(BookmarkService::class);

    expect($service->bookmarkedPostIds(null, [1, 2, 3]))->toBe([])
        ->and($service->bookmarkedPostIds(1, []))->toBe([]);
});

it('list for returns only the owners saves most recent first', function () {
    $owner = User::factory()->createOne();
    $other = User::factory()->createOne();
    $first = bookmarkPost($owner, 'first');
    $second = bookmarkPost($owner, 'second');
    $foreign = bookmarkPost($other, 'foreign');

    Bookmark::create(['user_id' => $owner->id, 'post_id' => $first->id, 'created_at' => now()->subDay()]);
    Bookmark::create(['user_id' => $owner->id, 'post_id' => $second->id, 'created_at' => now()]);
    Bookmark::create(['user_id' => $other->id, 'post_id' => $foreign->id]);

    $list = app(BookmarkService::class)->listFor($owner->id);

    expect($list->pluck('id')->all())->toBe([$second->id, $first->id]);
});

/** Ordering is by when it was saved, not when the post was published. */
it('list for orders by save time not publish time', function () {
    $user = User::factory()->createOne();
    $old = bookmarkPost($user, 'old');
    $old->update(['published_at' => now()->subYear(), 'created_at' => now()->subYear()]);
    $fresh = bookmarkPost($user, 'fresh');

    Bookmark::create(['user_id' => $user->id, 'post_id' => $fresh->id, 'created_at' => now()->subHour()]);
    Bookmark::create(['user_id' => $user->id, 'post_id' => $old->id, 'created_at' => now()]);

    $list = app(BookmarkService::class)->listFor($user->id);

    expect($list->pluck('id')->all())->toBe([$old->id, $fresh->id]);
});

it('list for hides unpublished posts', function () {
    $user = User::factory()->createOne();
    $post = bookmarkPost($user);
    Bookmark::create(['user_id' => $user->id, 'post_id' => $post->id]);

    $post->update(['status' => 'draft']);

    expect(app(BookmarkService::class)->listFor($user->id))->toHaveCount(0);
});

it('deleting a post cascades its bookmarks', function () {
    $user = User::factory()->createOne();
    $post = bookmarkPost($user);
    Bookmark::create(['user_id' => $user->id, 'post_id' => $post->id]);

    $post->forceDelete();

    assertDatabaseCount('bookmarks', 0);
});
