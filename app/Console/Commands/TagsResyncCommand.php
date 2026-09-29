<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Term;
use App\Services\TagService;
use App\Support\Markdown;
use App\Support\Slug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TagsResyncCommand extends Command
{
    protected $signature = 'orbita:tags-resync
        {--dry-run : Report which tags would be removed without changing anything}';

    protected $description = 'Re-extract hashtags for tagged posts and delete tags left without posts';

    public function handle(TagService $tags): int
    {
        $tagIds = Term::query()->where('taxonomy', 'tag')->pluck('id');

        $postIds = DB::table('term_relationships')
            ->whereIn('term_id', $tagIds)
            ->distinct()
            ->pluck('post_id');

        $this->line("Tagged posts: {$postIds->count()}");

        if ($this->option('dry-run')) {
            $this->comment('Mode: DRY RUN (no changes will be made)');

            $kept = [];
            Post::query()->whereIn('id', $postIds)->each(function (Post $post) use (&$kept) {
                $names = array_slice(
                    Markdown::extractHashtags(trim($post->title.' '.$post->content)),
                    0,
                    max(0, (int) config('orbita.posts.limit_tags', 10)),
                );
                foreach ($names as $name) {
                    $kept[Slug::make($name)] = true;
                }
            });

            $orphans = Term::query()
                ->where('taxonomy', 'tag')
                ->whereIn('id', $tagIds)
                ->whereNotIn('slug', array_keys($kept))
                ->whereHas('posts')
                ->pluck('slug');

            $this->line("Would remove {$orphans->count()} tag(s): ".$orphans->implode(', '));

            return self::SUCCESS;
        }

        $linked = Term::query()->where('taxonomy', 'tag')->whereHas('posts')->pluck('id');

        Post::query()->whereIn('id', $postIds)->each(
            fn (Post $post) => $tags->syncForPost($post, trim($post->title.' '.$post->content)),
        );

        $orphans = Term::query()
            ->where('taxonomy', 'tag')
            ->whereIn('id', $linked)
            ->whereDoesntHave('posts');

        $slugs = $orphans->pluck('slug');
        $orphans->delete();

        $this->info("Removed {$slugs->count()} tag(s): ".$slugs->implode(', '));

        return self::SUCCESS;
    }
}
