<?php

namespace App\Support;

use App\Models\Media;
use App\Services\BookmarkService;
use App\Services\ReactionService;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;

class FeedRowMeta
{
    /**
     * @param  list<int>  $ids
     * @return array{summaries: array, mine: array, bookmarked: array, images: array<int, Media>, reported: array, tags: array<int, list<object>>}
     */
    public static function for(array $ids): array
    {
        if ($ids === []) {
            return ['summaries' => [], 'mine' => [], 'bookmarked' => [], 'images' => [], 'reported' => [], 'tags' => []];
        }

        $reactions = app(ReactionService::class);
        $userId = auth()->id() ? (int) auth()->id() : null;

        return [
            'summaries' => $reactions->summariesFor('post', $ids),
            'mine' => $reactions->userReactionsFor($userId, 'post', $ids),
            'bookmarked' => app(BookmarkService::class)->bookmarkedPostIds($userId, $ids),
            'images' => self::firstImagesFor($ids),
            'reported' => array_flip(app(ReportService::class)->reportedIdsFor($userId, 'post', $ids)),
            'tags' => self::tagsFor($ids),
        ];
    }

    /**
     * @param  iterable<object>  $posts
     * @return list<int>
     */
    public static function idsOf(iterable $posts): array
    {
        $ids = [];
        foreach ($posts as $post) {
            $ids[] = (int) $post->id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<object>>
     */
    public static function tagsFor(array $ids): array
    {
        return DB::table('term_relationships as tr')
            ->join('terms as t', 't.id', '=', 'tr.term_id')
            ->whereIn('tr.post_id', $ids)
            ->where('t.taxonomy', 'tag')
            ->where('t.is_active', true)
            ->orderBy('t.name')
            ->get(['tr.post_id', 't.id', 't.slug', 't.name'])
            ->groupBy('post_id')
            ->map(fn ($rows) => $rows->values()->all())
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, Media>
     */
    private static function firstImagesFor(array $ids): array
    {
        return Media::query()
            ->join('media_relationship as mr', 'mr.media_id', '=', 'media.id')
            ->whereIn('mr.post_id', $ids)
            ->where('media.media_type', Media::TYPE_POST)
            ->orderBy('mr.display_order')
            ->orderBy('mr.media_id')
            ->get(['media.*', 'mr.post_id as post_id'])
            ->unique('post_id')
            ->keyBy('post_id')
            ->all();
    }
}
