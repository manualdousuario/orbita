<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Support\Antispam;
use App\Support\HashId;
use App\Support\Markdown;
use App\Support\MentionNotifier;
use App\Support\ReferralLinks;
use App\Support\Slug;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enforces post business rules: antispam, edit windows, and post lifecycle.
 */
class PostService
{
    /**
     * Antispam gate for new posts: cooldown, per-hour limit, duplicate detection.
     *
     * @return array{allowed: bool, reason?: string}
     */
    public function checkAntispam(
        int $userId,
        ?string $title = null,
        ?string $url = null,
        ?string $content = null,
    ): array {
        $cooldown = (int) config('orbita.antispam.post_cooldown', 300);
        if ($cooldown > 0) {
            $last = Post::query()
                ->where('user_id', $userId)
                ->whereNull('version_of')
                ->latest('created_at')
                ->value('created_at');

            if ($last !== null) {
                $elapsed = time() - $last->getTimestamp();
                if ($elapsed < $cooldown) {
                    $wait = Antispam::humanizeWait($cooldown - $elapsed);

                    return ['allowed' => false, 'reason' => "Aguarde {$wait} antes de publicar novamente."];
                }
            }
        }

        $maxPerHour = (int) config('orbita.antispam.max_posts_per_hour', 5);
        if ($maxPerHour > 0) {
            $hourAgo = date('Y-m-d H:i:s', time() - 3600);
            $recent = Post::query()
                ->where('user_id', $userId)
                ->whereNull('version_of')
                ->where('created_at', '>=', $hourAgo)
                ->count();

            if ($recent >= $maxPerHour) {
                return ['allowed' => false, 'reason' => 'Você atingiu o limite de posts por hora.'];
            }
        }

        if (config('orbita.antispam.duplicate_detection', true)) {
            $title = $title !== null ? trim($title) : null;
            $url = $url !== null && trim($url) !== '' ? trim($url) : null;
            $content = $content !== null ? trim($content) : null;

            $url = ReferralLinks::clean($url);
            $content = $content !== null ? ReferralLinks::cleanText($content)['content'] : null;

            $hasSignal = $url !== null
                || ($title !== null && $title !== '')
                || ($content !== null && $content !== '');

            if ($hasSignal) {
                $hourAgo = date('Y-m-d H:i:s', time() - 3600);

                $dupExists = Post::query()
                    ->where('user_id', $userId)
                    ->whereNull('version_of')
                    ->where('created_at', '>=', $hourAgo)
                    ->where(function ($q) use ($title, $url, $content) {
                        if ($url !== null) {
                            $q->orWhere('url', $url);
                        }
                        if ($title !== null && $title !== '') {
                            $q->orWhere('title', $title);
                        }
                        if ($content !== null && $content !== '') {
                            $q->orWhere('content', $content);
                        }
                    })
                    ->exists();

                if ($dupExists) {
                    return ['allowed' => false, 'reason' => 'Você já publicou este conteúdo recentemente.'];
                }
            }
        }

        return ['allowed' => true];
    }

    /**
     * Builds the query for inactive published posts past the auto-close cutoff.
     */
    public function inactivePostsQuery(?int $days = null): Builder
    {
        $days = $days ?? (int) config('orbita.posts.close_inactive_days', 30);

        if ($days <= 0) {
            return Post::query()->whereRaw('1 = 0');
        }

        $cutoff = Carbon::now()->subDays($days);

        return Post::query()
            ->where('status', 'published')
            ->whereNull('version_of')
            ->where(function (Builder $q) use ($cutoff) {
                $q->where(function (Builder $noComments) use ($cutoff) {
                    $noComments->where('comment_count', 0)
                        ->where('published_at', '<', $cutoff);
                })->orWhere(function (Builder $stale) use ($cutoff) {
                    $stale->where('comment_count', '>', 0)
                        ->whereDoesntHave('comments', function (Builder $c) use ($cutoff) {
                            $c->whereNull('version_of')
                                ->where('created_at', '>=', $cutoff);
                        });
                });
            });
    }

    /**
     * Closes inactive published posts and locks their comments; returns rows updated.
     */
    public function closeInactivePosts(?int $days = null): int
    {
        return $this->inactivePostsQuery($days)->update([
            'status' => 'closed',
            'allow_comments' => false,
        ]);
    }

    /**
     * Whether the user may edit this post (staff bypass, ownership, edit window).
     */
    public function canEdit(Post $post, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if ($user === null) {
            return false;
        }

        if ($this->isStaff($user)) {
            return true;
        }

        if ((int) $user->id !== (int) $post->user_id) {
            return false;
        }

        return $this->withinEditWindow($post);
    }

    public function withinEditWindow(Post $post): bool
    {
        $limit = (int) config('orbita.posts.edit_time_limit', 900);

        if ($limit <= 0) {
            return true;
        }

        return (time() - ($post->created_at?->getTimestamp() ?? time())) <= $limit;
    }

    public function assertWithinEditWindow(Post $post, ?User $user = null): void
    {
        $user ??= Auth::user();

        if ($user !== null && $this->isStaff($user)) {
            return;
        }

        if (! $this->withinEditWindow($post)) {
            throw ValidationException::withMessages([
                'error' => 'O tempo limite para edição expirou.',
            ]);
        }
    }

    private function isStaff(User $user): bool
    {
        return $user->isStaff();
    }

    /**
     * Throws on content that violates post rules (raw HTML, headings, remote images).
     *
     * @throws RuntimeException on the first violation
     */
    public function assertContentIsAllowed(string $content): void
    {
        $issues = Markdown::validate($content);

        if ($issues !== []) {
            throw new \RuntimeException($issues[0]);
        }
    }

    /**
     * Creates a post, assigning slug, published_at, and a persisted hashid.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPost(array $data): Post
    {
        $this->assertContentIsAllowed((string) ($data['content'] ?? ''));

        $stripped = $this->stripReferralParams($data);

        if (($data['status'] ?? 'published') === 'published' && ! isset($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        if (! empty($data['title'])) {
            $data['slug'] = Slug::make((string) $data['title']);
        }

        $post = DB::transaction(function () use ($data) {
            $data['hashid'] = HashId::unique('posts');
            $post = Post::create($data);

            if (($data['status'] ?? 'published') === 'published') {
                app(TagService::class)->syncForPost(
                    $post,
                    trim(($data['title'] ?? '').' '.($data['content'] ?? '')),
                );
            }

            return $post;
        });

        ReferralLinks::report($post, $stripped);

        if ($post->status === 'published') {
            $this->dispatchMentionNotifications($post, null);
        }

        return $post;
    }

    /**
     * Removes forbidden referral parameters in place; returns the names removed.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function stripReferralParams(array &$data): array
    {
        $stripped = [];

        if (isset($data['content'])) {
            $result = ReferralLinks::cleanText((string) $data['content']);
            $data['content'] = $result['content'];
            $stripped = $result['stripped'];
        }

        if (! empty($data['url'])) {
            $result = ReferralLinks::cleanLink((string) $data['url']);
            $data['url'] = $result['url'];
            $stripped = array_merge($stripped, $result['stripped']);
        }

        return array_values(array_unique($stripped));
    }

    /**
     * Notifies users newly @mentioned, skipping any already mentioned before the edit.
     */
    private function dispatchMentionNotifications(Post $post, ?string $previousContent): void
    {
        $author = User::query()->find($post->user_id);
        if ($author === null) {
            return;
        }

        $link = rtrim((string) config('orbita.url'), '/').'/p/'.$post->hashid;
        $message = sprintf(
            '%s mencionou você no post "%s"',
            $author->display_name ?? $author->username,
            $post->title,
        );

        MentionNotifier::dispatch((string) $post->content, $previousContent, $message, $link, (int) $author->id);
    }

    /**
     * Updates a post, snapshotting a revision when content changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePost(Post $post, array $data, bool $saveVersion = true): Post
    {
        $actor = Auth::user();

        if ($actor !== null && ! $this->canEdit($post, $actor)) {
            throw new AuthorizationException('Você não pode editar esta publicação.');
        }

        if (isset($data['content']) && $data['content'] !== $post->content) {
            $this->assertContentIsAllowed((string) $data['content']);
        }

        $submitted = [];
        if (isset($data['content']) && $data['content'] !== $post->content) {
            $submitted['content'] = $data['content'];
        }
        if (array_key_exists('url', $data) && $data['url'] !== $post->url) {
            $submitted['url'] = $data['url'];
        }

        $stripped = $this->stripReferralParams($submitted);
        $data = array_merge($data, $submitted);

        $contentChanged =
            (isset($data['title']) && $data['title'] !== $post->title) ||
            (isset($data['content']) && $data['content'] !== $post->content) ||
            (array_key_exists('url', $data) && $data['url'] !== $post->url);

        if ($saveVersion && $contentChanged) {
            $this->saveVersionBeforeUpdate($post);
        }

        if (isset($data['title']) && $data['title'] !== $post->title) {
            $data['slug'] = Slug::make((string) $data['title']);
        }

        $data['edited_at'] = date('Y-m-d H:i:s');

        $titleOrContentChanged =
            (isset($data['title']) && $data['title'] !== $post->title) ||
            (isset($data['content']) && $data['content'] !== $post->content);
        $wasPublished = $post->status === 'published';
        $bodyChanged = isset($data['content']) && $data['content'] !== $post->content;
        $previousContent = (string) $post->content;

        $post->update($data);

        ReferralLinks::report($post, $stripped);

        if ($post->status === 'published' && ($titleOrContentChanged || ! $wasPublished)) {
            app(TagService::class)->syncForPost($post, trim($post->title.' '.$post->content));
        }

        if ($post->status === 'published' && ($bodyChanged || ! $wasPublished)) {
            $this->dispatchMentionNotifications($post, $wasPublished ? $previousContent : null);
        }

        return $post;
    }

    /**
     * Persists an immutable revision snapshot of the post.
     */
    public function saveVersionBeforeUpdate(Post $post): void
    {
        DB::transaction(function () use ($post) {
            $revisionCount = Post::query()->where('version_of', $post->id)->count();

            Post::create([
                'version_of' => $post->id,
                'user_id' => $post->user_id,
                'hashid' => HashId::unique('posts'),
                'title' => $post->title,
                'url' => $post->url,
                'slug' => $post->slug.'-v'.($revisionCount + 1),
                'content' => $post->content,
                'status' => 'revision',
                'is_pinned' => false,
                'allow_comments' => false,
                'score' => (int) ($post->score ?? 0),
                'comment_count' => (int) ($post->comment_count ?? 0),
                'reaction_count' => (int) ($post->reaction_count ?? 0),
                'edited_at' => $post->edited_at ?? $post->created_at,
                'published_at' => $post->published_at,
            ]);
        });
    }
}
