<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CommentCreated;
use App\Events\CommentReplied;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\Antispam;
use App\Support\HashId;
use App\Support\Markdown;
use App\Support\MentionNotifier;
use App\Support\ReferralLinks;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Enforces comment business rules: antispam, edit windows, nesting depth, and lifecycle.
 */
class CommentService
{
    public static function rootOrdering(string $sort): array
    {
        return match ($sort) {
            'oldest' => ['created_at', 'asc'],
            'most_reactions' => ['score', 'desc'],
            default => ['created_at', 'desc'],
        };
    }

    public function rootCountFor(int $postId): int
    {
        return Comment::query()
            ->where('post_id', $postId)
            ->where('status', 'visible')
            ->whereNull('version_of')
            ->where(function ($q) use ($postId) {
                $q->whereNull('parent_id')
                    ->orWhereNotIn('parent_id', function ($sub) use ($postId) {
                        $sub->select('id')->from('comments')
                            ->where('post_id', $postId)
                            ->where('status', 'visible')
                            ->whereNull('version_of')
                            ->whereNull('deleted_at');
                    });
            })
            ->count();
    }

    public function rootPageOf(Comment $comment, ?string $sort = null, ?int $perPage = null): int
    {
        $sort ??= (string) config('orbita.comments.default_sort', 'newest');
        $perPage = max(1, $perPage ?? (int) config('orbita.comments.per_page', 20));

        $all = Comment::query()
            ->where('post_id', $comment->post_id)
            ->where('status', 'visible')
            ->whereNull('version_of')
            ->get(['id', 'parent_id', 'created_at', 'score']);

        $byId = $all->keyBy(fn (Comment $c) => (int) $c->id);

        $rootId = (int) $comment->id;
        $guard = 0;
        while (($node = $byId->get($rootId)) !== null && $node->parent_id !== null && $guard++ < 50) {
            if (! $byId->has((int) $node->parent_id)) {
                break;
            }
            $rootId = (int) $node->parent_id;
        }

        [$column, $direction] = self::rootOrdering($sort);

        $position = $all
            ->filter(fn (Comment $c) => $c->parent_id === null || ! $byId->has((int) $c->parent_id))
            ->sort(function (Comment $a, Comment $b) use ($column, $direction) {
                $cmp = $a->{$column} <=> $b->{$column};
                $cmp = $direction === 'asc' ? $cmp : -$cmp;

                return $cmp ?: ($direction === 'asc' ? $a->id <=> $b->id : $b->id <=> $a->id);
            })
            ->values()
            ->search(fn (Comment $c) => (int) $c->id === $rootId);

        return $position === false ? 1 : (int) floor($position / $perPage) + 1;
    }

    public function permalinkFor(Comment $comment, Post $post, ?string $sort = null): string
    {
        $page = $this->rootPageOf($comment, $sort);

        $url = rtrim((string) config('orbita.url'), '/').'/p/'.$post->hashid;

        if ($page > 1) {
            $url .= '?comentarios='.$page;
        }

        return $url.'#comment-'.$comment->hashid;
    }

    /**
     * Antispam gate for new comments: cooldown, per-hour limit, duplicate detection.
     *
     * @param  int|null  $postId  target post, required for duplicate detection
     * @param  string|null  $content  comment body, required for duplicate detection
     * @return array{allowed: bool, reason?: string}
     */
    public function checkAntispam(int $userId, ?int $postId = null, ?string $content = null): array
    {
        $cooldown = (int) config('orbita.antispam.comment_cooldown', 30);
        if ($cooldown > 0) {
            $last = Comment::query()
                ->where('user_id', $userId)
                ->whereNull('version_of')
                ->latest('created_at')
                ->value('created_at');

            if ($last !== null) {
                $elapsed = time() - $last->getTimestamp();
                if ($elapsed < $cooldown) {
                    $wait = Antispam::humanizeWait($cooldown - $elapsed);

                    return ['allowed' => false, 'reason' => "Aguarde {$wait} antes de comentar novamente."];
                }
            }
        }

        $maxPerHour = (int) config('orbita.antispam.max_comments_per_hour', 20);
        if ($maxPerHour > 0) {
            $hourAgo = date('Y-m-d H:i:s', time() - 3600);
            $recent = Comment::query()
                ->where('user_id', $userId)
                ->whereNull('version_of')
                ->where('created_at', '>=', $hourAgo)
                ->count();

            if ($recent >= $maxPerHour) {
                return ['allowed' => false, 'reason' => 'Você atingiu o limite de comentários por hora.'];
            }
        }

        if (config('orbita.antispam.duplicate_detection', true) && $postId !== null) {
            $body = ReferralLinks::cleanText(trim((string) $content))['content'];

            if ($body !== '') {
                $hourAgo = date('Y-m-d H:i:s', time() - 3600);

                $dupExists = Comment::query()
                    ->where('user_id', $userId)
                    ->whereNull('version_of')
                    ->where('post_id', $postId)
                    ->where('content', $body)
                    ->where('created_at', '>=', $hourAgo)
                    ->exists();

                if ($dupExists) {
                    return ['allowed' => false, 'reason' => 'Você já publicou este comentário recentemente.'];
                }
            }
        }

        return ['allowed' => true];
    }

    /**
     * Whether the user may edit this comment (staff bypass, ownership, edit window).
     */
    public function canEdit(Comment $comment, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        if ((int) $user->id !== (int) $comment->user_id) {
            return false;
        }

        return $this->withinEditWindow($comment);
    }

    /**
     * Throws on content that violates comment rules (raw HTML, headings, remote images).
     *
     * @throws RuntimeException on the first violation
     */
    public function assertContentIsAllowed(string $content): void
    {
        $issues = Markdown::validate($content);

        if ($issues !== []) {
            throw new RuntimeException($issues[0]);
        }
    }

    /** True when the comment is still inside its edit window (0 = unlimited). */
    public function withinEditWindow(Comment $comment): bool
    {
        $limit = (int) config('orbita.comments.edit_time_limit', 900);

        if ($limit <= 0) {
            return true;
        }

        $created = ($comment->created_at ?? now())->getTimestamp();

        return (time() - $created) <= $limit;
    }

    /**
     * Asserts the comment is still inside its editable window.
     *
     * @throws ValidationException
     */
    public function assertWithinEditWindow(Comment $comment): void
    {
        if (! $this->withinEditWindow($comment)) {
            throw ValidationException::withMessages([
                'error' => 'O tempo limite para edição expirou.',
            ]);
        }
    }

    public function maxNestingLevel(): int
    {
        return (int) config('orbita.comments.max_nesting_level', 5);
    }

    /**
     * Creates a comment, enforcing nesting depth and content rules.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException when the maximum nesting level is exceeded
     */
    public function createComment(array $data): Comment
    {
        $post = Post::query()->find($data['post_id'] ?? null);
        if ($post === null) {
            throw new RuntimeException('Post não encontrado.');
        }
        if (! $post->allow_comments) {
            throw new RuntimeException('Os comentários estão fechados para este post.');
        }

        $this->assertContentIsAllowed((string) ($data['content'] ?? ''));

        $sanitized = ReferralLinks::cleanText((string) ($data['content'] ?? ''));
        $data['content'] = $sanitized['content'];

        if (! empty($data['parent_id'])) {
            $parent = Comment::query()->find($data['parent_id']);
            if ($parent) {
                $data['nesting_level'] = (int) $parent->nesting_level + 1;

                if ($data['nesting_level'] > $this->maxNestingLevel()) {
                    throw new RuntimeException('Maximum nesting level exceeded');
                }
            } else {
                $data['nesting_level'] = 0;
            }
        } else {
            $data['nesting_level'] = 0;
        }

        if (! isset($data['status'])) {
            $data['status'] = 'visible';
        }

        $comment = DB::transaction(function () use ($data) {
            $data['hashid'] = HashId::unique('comments');

            return Comment::create($data);
        });

        ReferralLinks::report($comment, $sanitized['stripped']);

        $this->dispatchCommentEvents($comment);
        $this->dispatchMentionNotifications($comment, null);

        return $comment;
    }

    /**
     * Notifies users newly @mentioned, skipping any already mentioned before the edit.
     */
    private function dispatchMentionNotifications(Comment $comment, ?string $previousContent): void
    {
        $author = User::query()->find($comment->user_id);
        $post = Post::query()->find($comment->post_id);
        if ($author === null || $post === null) {
            return;
        }

        $link = $this->permalinkFor($comment, $post);
        $message = sprintf(
            '%s mencionou você em um comentário no post "%s"',
            $author->display_name ?? $author->username,
            $post->title,
        );

        MentionNotifier::dispatch((string) $comment->content, $previousContent, $message, $link, (int) $author->id);
    }

    /**
     * Dispatches CommentReplied for replies, CommentCreated for top-level comments.
     */
    private function dispatchCommentEvents(Comment $comment): void
    {
        $author = User::query()->find($comment->user_id);
        if ($author === null) {
            return;
        }

        if ($comment->parent_id !== null) {
            $parent = Comment::query()->find($comment->parent_id);
            if ($parent !== null) {
                event(new CommentReplied($parent, $comment, $author));

                return;
            }
        }

        $post = Post::query()->find($comment->post_id);
        if ($post !== null) {
            event(new CommentCreated($post, $comment, $author));
        }
    }

    /**
     * Updates a comment, snapshotting a revision when content changes.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException when the edit time limit has passed
     */
    public function updateComment(Comment $comment, array $data, bool $saveVersion = true): Comment
    {
        if (! $this->canEdit($comment)) {
            throw new RuntimeException('Edit time limit exceeded');
        }

        $contentChanged = isset($data['content']) && $data['content'] !== $comment->content;
        $previousContent = (string) $comment->content;

        $stripped = [];

        if ($contentChanged) {
            $this->assertContentIsAllowed((string) $data['content']);

            $sanitized = ReferralLinks::cleanText((string) $data['content']);
            $data['content'] = $sanitized['content'];
            $stripped = $sanitized['stripped'];
        }

        if ($saveVersion && $contentChanged) {
            $this->saveVersionBeforeUpdate($comment);
        }

        $data['edited_at'] = date('Y-m-d H:i:s');

        $comment->update($data);

        ReferralLinks::report($comment, $stripped);

        if ($contentChanged) {
            $this->dispatchMentionNotifications($comment, $previousContent);
        }

        return $comment;
    }

    /**
     * Persists an immutable revision snapshot of the comment.
     */
    public function saveVersionBeforeUpdate(Comment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $revision = Comment::create([
                'version_of' => $comment->id,
                'post_id' => $comment->post_id,
                'parent_id' => $comment->parent_id,
                'user_id' => $comment->user_id,
                'hashid' => HashId::unique('comments'),
                'content' => $comment->content,
                'nesting_level' => $comment->nesting_level,
                'status' => 'revision',
                'score' => (int) ($comment->score ?? 0),
                'reaction_count' => (int) ($comment->reaction_count ?? 0),
                'edited_at' => $comment->edited_at ?? $comment->created_at,
            ]);
        });
    }
}
