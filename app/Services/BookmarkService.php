<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Saves posts and batches bookmark lookups for feeds.
 */
class BookmarkService
{
    /**
     * Add or remove the user's bookmark for a post.
     *
     * @return bool the state *after* the call: true = saved, false = not saved
     */
    public function toggle(int $userId, int $postId): bool
    {
        $existing = Bookmark::query()
            ->where('user_id', $userId)
            ->where('post_id', $postId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        try {
            Bookmark::create(['user_id' => $userId, 'post_id' => $postId]);
        } catch (UniqueConstraintViolationException) {
            // Race between SELECT and INSERT; the unique index enforces one bookmark per pair.
        }

        return true;
    }

    public function isBookmarked(int $userId, int $postId): bool
    {
        return Bookmark::query()
            ->where('user_id', $userId)
            ->where('post_id', $postId)
            ->exists();
    }

    /**
     * Which of these posts the user has saved, in one query.
     *
     * @param  list<int>  $postIds
     * @return array<int, true> post id => true, for O(1) `isset()` lookups in the view
     */
    public function bookmarkedPostIds(?int $userId, array $postIds): array
    {
        if ($userId === null || $postIds === []) {
            return [];
        }

        return DB::table('bookmarks')
            ->where('user_id', $userId)
            ->whereIn('post_id', $postIds)
            ->pluck('post_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * The user's saved posts, most recently saved first.
     *
     * @return LengthAwarePaginator<int, Post>
     */
    public function listFor(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Post::query()
            ->join('bookmarks', 'bookmarks.post_id', '=', 'posts.id')
            ->where('bookmarks.user_id', $userId)
            ->whereIn('posts.status', ['published', 'closed'])
            ->with('user')
            ->orderByDesc('bookmarks.created_at')
            ->select('posts.*')
            ->paginate($perPage);
    }
}
