<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Term;
use App\Services\MetaTagsService;
use Illuminate\Contracts\View\View;

/**
 * Public listing of posts under a tag taxonomy term.
 */
class TagController extends Controller
{
    /**
     * GET /t/{slug} — active tag only; 404 when the tag is missing or an admin has deactivated it.
     */
    public function show(string $slug): View
    {
        $tag = Term::query()
            ->where('taxonomy', 'tag')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $total = Post::query()
            ->whereIn('status', ['published', 'closed'])
            ->whereNull('version_of')
            ->whereHas('terms', fn ($query) => $query->where('terms.id', $tag->id))
            ->count();

        $items = Post::query()
            ->whereIn('status', ['published', 'closed'])
            ->whereNull('version_of')
            ->whereHas('terms', fn ($query) => $query->where('terms.id', $tag->id))
            ->orderByDesc('published_at')
            ->limit(10)
            ->get(['hashid', 'slug', 'title'])
            ->map(fn (Post $post): array => [
                'title' => (string) $post->title,
                'url' => route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]),
            ])
            ->all();

        $meta = app(MetaTagsService::class)->forTag(
            $tag,
            $tag->description ?: 'Posts com a tag #'.$tag->name.'.',
            $items,
        );

        return view('tags.show', [
            'tag' => $tag,
            'total' => $total,
            'meta' => $meta,
        ]);
    }
}
