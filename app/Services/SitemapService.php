<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

/**
 * Generates the sitemap set: pages, chunked posts, and the index.
 */
class SitemapService
{
    public const INDEX = 'sitemap.xml';

    public const PAGES = 'sitemap-pages.xml';

    /** Held while writing so concurrent crawler hits on a cold cache build once, not N times. */
    private const LOCK = 'orbita:sitemap-generate';

    public function directory(): string
    {
        return rtrim((string) config('orbita.sitemap.cache_dir'), '/\\');
    }

    public function path(string $file): string
    {
        return $this->directory().'/'.$file;
    }

    public static function postsFile(int $page): string
    {
        return "sitemap-posts-{$page}.xml";
    }

    public function has(string $file): bool
    {
        return File::exists($this->path($file));
    }

    /**
     * Returns the cached file's path, building the whole set if missing.
     *
     * @throws LockTimeoutException when another build holds the lock
     */
    public function ensure(string $file): ?string
    {
        if ($this->has($file)) {
            return $this->path($file);
        }

        Cache::lock(self::LOCK, 300)->block(10, function () use ($file) {
            if (! $this->has($file)) {
                $this->generate();
            }
        });

        return $this->has($file) ? $this->path($file) : null;
    }

    /**
     * Write the full set. Returns the number of post files written.
     *
     * @param  string|null  $dir  Output directory; defaults to the storage cache directory.
     */
    public function generate(?string $dir = null): int
    {
        $dir = rtrim($dir ?: $this->directory(), '/\\');
        File::ensureDirectoryExists($dir);

        $index = SitemapIndex::create();

        $this->writePages($dir, $index);
        $postFiles = $this->writePosts($dir, $index);

        $index->writeToFile($dir.'/'.self::INDEX);

        $this->pruneStalePostFiles($dir, $postFiles);

        return $postFiles;
    }

    private function writePages(string $dir, SitemapIndex $index): void
    {
        $sitemap = Sitemap::create()->add(
            Url::create(route('home'))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_HOURLY)
                ->setPriority(1.0),
        );

        Page::query()
            ->where('is_active', true)
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (Page $page) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('pages.show', ['slug' => $page->slug]))
                        ->setLastModificationDate($page->updated_at ?? now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.5),
                );
            });

        $this->addTags($sitemap);
        $this->addProfiles($sitemap);

        $sitemap->writeToFile($dir.'/'.self::PAGES);
        $index->add(route('sitemap.pages'));
    }

    private function addTags(Sitemap $sitemap): void
    {
        Term::query()
            ->where('taxonomy', 'tag')
            ->where('usage_count', '>', 0)
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (Term $tag) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('tags.show', ['slug' => $tag->slug]))
                        ->setLastModificationDate($tag->updated_at ?? now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.4),
                );
            });
    }

    private function addProfiles(Sitemap $sitemap): void
    {
        User::query()
            ->whereNull('anonymized_at')
            ->where('is_banned', false)
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('posts')
                ->whereColumn('posts.user_id', 'users.id')
                ->whereNull('posts.version_of')
                ->whereNull('posts.deleted_at')
                ->whereIn('posts.status', ['published', 'closed']))
            ->orderBy('username')
            ->get(['username', 'updated_at'])
            ->each(function (User $user) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('users.profile', ['username' => $user->username]))
                        ->setLastModificationDate($user->updated_at ?? now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.3),
                );
            });
    }

    private function writePosts(string $dir, SitemapIndex $index): int
    {
        $fileNumber = 0;

        Post::query()
            ->whereNull('version_of')
            ->whereIn('status', ['published', 'closed'])
            ->orderBy('id')
            ->select(['id', 'hashid', 'slug', 'updated_at', 'last_comment_at'])
            ->chunkById((int) config('orbita.sitemap.posts_per_file'), function ($posts) use (&$fileNumber, $dir, $index) {
                $fileNumber++;
                $sitemap = Sitemap::create();

                foreach ($posts as $post) {
                    $sitemap->add(
                        Url::create(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
                            ->setLastModificationDate($this->postLastModified($post))
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.8),
                    );
                }

                $sitemap->writeToFile($dir.'/'.self::postsFile($fileNumber));
                $index->add(route('sitemap.posts', ['page' => $fileNumber]));
            });

        return $fileNumber;
    }

    private function postLastModified(Post $post): \DateTimeInterface
    {
        $candidates = array_filter([$post->updated_at, $post->last_comment_at]);

        if ($candidates === []) {
            return now();
        }

        return max($candidates);
    }

    private function pruneStalePostFiles(string $dir, int $keptFiles): void
    {
        foreach (File::glob($dir.'/sitemap-posts-*.xml') as $existing) {
            if (preg_match('/sitemap-posts-(\d+)\.xml$/', $existing, $matches) && (int) $matches[1] > $keptFiles) {
                File::delete($existing);
            }
        }
    }
}
