<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Term;
use App\Support\Markdown;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Laminas\Feed\Writer\Feed;

/**
 * RSS feeds for all published posts and per tag.
 */
class FeedController extends Controller
{
    /** GET /feed — latest published posts. */
    public function posts(): Response
    {
        $appName = (string) config('orbita.name');

        return $this->xmlResponse($this->generateRss(
            $appName,
            'Últimos posts de '.$appName,
            $this->recentPosts()->get(),
        ));
    }

    /** GET /feed/tag/{slug} */
    public function tag(string $slug): Response
    {
        $appName = (string) config('orbita.name');
        $tag = Term::query()->where('taxonomy', 'tag')->where('slug', $slug)->first();

        if (! $tag) {
            return $this->xmlResponse('<?xml version="1.0"?><rss version="2.0"><channel><title>Tag não encontrada</title></channel></rss>');
        }

        $posts = $this->recentPosts()
            ->whereHas('terms', fn ($query) => $query->where('terms.id', $tag->id))
            ->get();

        return $this->xmlResponse($this->generateRss(
            $appName.' - #'.$tag->name,
            'Últimos posts em '.$appName.' com a tag #'.$tag->name,
            $posts,
        ));
    }

    /** Base query: published, canonical (non-revision) posts, newest first. */
    private function recentPosts()
    {
        return Post::query()
            ->with('user')
            ->whereIn('status', ['published', 'closed'])
            ->whereNull('version_of')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit((int) config('orbita.pagination.feed_limit'));
    }

    /**
     * @param  Collection<int, Post>|iterable<Post>  $posts
     */
    private function generateRss(string $title, string $description, iterable $posts): string
    {
        $baseUrl = rtrim((string) config('orbita.url', config('app.url')), '/');

        $feed = new Feed;
        $feed->setTitle($title);
        $feed->setLink($baseUrl);
        $feed->setDescription($description);
        $feed->setLanguage('pt-BR');
        $feed->setDateModified(time());
        $feed->setFeedLink($baseUrl.'/feed', 'rss');

        foreach ($posts as $post) {
            $url = route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);

            $excerpt = '';
            if (! empty($post->content)) {
                $excerpt = Markdown::excerpt((string) $post->content, 300);
            } elseif (! empty($post->url)) {
                $excerpt = 'Link: '.$post->url;
            }

            $entry = $feed->createEntry();
            $entry->setTitle((string) $post->title);
            $entry->setLink($url);
            $entry->setId($url);
            $entry->setDescription($excerpt !== '' ? $excerpt : (string) $post->title);
            $entry->setDateCreated(strtotime((string) ($post->published_at ?? $post->created_at ?? 'now')));
            $entry->setDateModified(strtotime((string) ($post->updated_at ?? $post->created_at ?? 'now')));

            $author = $post->user?->display_name ?: $post->user?->username;
            if ($author) {
                $entry->addAuthor(['name' => $author]);
            }

            $feed->addEntry($entry);
        }

        return $feed->export('rss');
    }

    private function xmlResponse(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
