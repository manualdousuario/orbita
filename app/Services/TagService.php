<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Term;
use App\Support\Markdown;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

/**
 * Turns #hashtags in post text into tag terms linked to the post.
 */
class TagService
{
    /**
     * Syncs the post's tags to the capped, de-duplicated hashtags in the text.
     */
    public function syncForPost(Post $post, string $text): void
    {
        $limit = max(0, (int) config('orbita.posts.limit_tags', 10));
        $names = array_slice(Markdown::extractHashtags($text), 0, $limit);

        $termIds = [];
        foreach ($names as $name) {
            $slug = Slug::make($name);
            if ($slug === '') {
                continue;
            }

            $term = Term::firstOrCreate(
                ['taxonomy' => 'tag', 'slug' => $slug],
                ['name' => $name, 'is_active' => true],
            );
            $termIds[$term->id] = $term->id;
        }

        $existingTagIds = $post->terms()->where('taxonomy', 'tag')->pluck('terms.id')->all();

        DB::transaction(function () use ($post, $existingTagIds, $termIds) {
            $post->terms()->detach($existingTagIds);
            if ($termIds !== []) {
                $post->terms()->attach(array_values($termIds));
            }

            $this->refreshUsageCounts(array_unique(array_merge($existingTagIds, array_values($termIds))));
        });
    }

    /**
     * Recomputes usage_count from term_relationships for the affected tags.
     *
     * @param  array<int, int>  $termIds
     */
    private function refreshUsageCounts(array $termIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $termIds)));
        if ($ids === []) {
            return;
        }

        // One correlated UPDATE for all affected tags instead of a per-id loop.
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::update(
            "UPDATE terms t
             SET t.usage_count = (
                 SELECT COUNT(*) FROM term_relationships tr WHERE tr.term_id = t.id
             )
             WHERE t.id IN ({$placeholders})",
            $ids,
        );
    }
}
