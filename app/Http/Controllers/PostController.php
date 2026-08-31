<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Services\BookmarkService;
use App\Services\CommentService;
use App\Services\MetaTagsService;
use App\Services\OembedService;
use App\Services\PostService;
use App\Services\ReactionService;
use App\Support\Markdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Web (Blade + Livewire) surface for posts.
 */
class PostController extends Controller
{
    public function __construct(private readonly PostService $postService) {}

    /**
     * GET /p/{hashid}/{slug?} — post detail with comment tree.
     */
    public function show(string $hashid, ?string $slug = null): View
    {
        $post = Post::query()
            ->with('user', 'media', 'terms')
            ->where('hashid', $hashid)
            ->whereNull('version_of')
            ->whereIn('status', ['published', 'closed'])
            ->firstOrFail();

        $contentHtml = Markdown::toHtml((string) $post->content);

        // Best-effort oEmbed for link posts; video embeds get a fixed 16:9 box.
        $oembedHtml = null;
        $oembedType = null;
        if (! empty($post->url)) {
            $oembed = app(OembedService::class);
            $provider = $oembed->checkUrl((string) $post->url);
            if ($provider !== null) {
                $data = $oembed->fetchOembed((string) $post->url, $provider['provider']);
                if (! empty($data['html'])) {
                    $oembedHtml = $data['html'];
                    $oembedType = $data['type'] ?? null;
                }
            }
        }

        $meta = app(MetaTagsService::class)->forPost([
            'title' => $post->title,
            'content_html' => $contentHtml,
            'hash_id' => $post->hashid,
            'slug' => $post->slug,
            'url' => $post->url,
            'created_at' => (string) $post->created_at,
            'published_at' => (string) ($post->published_at ?? $post->created_at),
            'updated_at' => (string) ($post->edited_at ?? $post->updated_at ?? $post->created_at),
            'comment_count' => (int) $post->comment_count,
            'reaction_count' => (int) $post->reaction_count,
            'tags' => $post->terms->map(fn ($term): array => ['name' => (string) $term->name])->all(),
            'author' => $post->user ? [
                'username' => $post->user->isLinkable() ? $post->user->username : null,
                'display_name' => $post->user->authorName(),
            ] : null,
            'comments' => $this->commentsForSchema($post),
        ]);

        return view('posts.show', [
            'post' => $post,
            'commentRootCount' => app(CommentService::class)->rootCountFor((int) $post->id),
            'contentHtml' => $contentHtml,
            'oembedHtml' => $oembedHtml,
            'oembedType' => $oembedType,
            'meta' => $meta,
            'canEdit' => $this->canEdit($post),
            'canDelete' => $this->canDelete($post),
            'canAdmin' => auth()->user()?->isStaff() ?? false,
            'isBookmarked' => auth()->check()
                && app(BookmarkService::class)->isBookmarked((int) auth()->id(), (int) $post->id),
            'reactionSummary' => app(ReactionService::class)->summaryForUser('post', (int) $post->id, auth()->id() ? (int) auth()->id() : null),
        ]);
    }

    private function commentsForSchema(Post $post): array
    {
        $postUrl = route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);

        return Comment::query()
            ->with('user:id,username,display_name,anonymized_at')
            ->where('post_id', $post->id)
            ->where('status', 'visible')
            ->whereNull('parent_id')
            ->whereNull('version_of')
            ->orderBy('created_at')
            ->limit(10)
            ->get(['id', 'hashid', 'user_id', 'content', 'created_at', 'reaction_count'])
            ->map(fn (Comment $comment): array => [
                'text' => (string) $comment->content,
                'created_at' => (string) $comment->created_at,
                'url' => $postUrl.'#comment-'.$comment->hashid,
                'reaction_count' => (int) $comment->reaction_count,
                'author' => $comment->user ? [
                    'username' => $comment->user->isLinkable() ? $comment->user->username : null,
                    'display_name' => $comment->user->authorName(),
                ] : null,
            ])
            ->all();
    }

    /**
     * GET /posts/create — create form (mounts the post-composer component).
     */
    public function create(): View
    {
        return view('posts.create');
    }

    /**
     * POST /posts — create a post. Progressive-enhancement / no-JS fallback for
     * the post-composer Livewire component; both call PostService. Images are
     * embedded inline in the Markdown body by the JS editor, so this no-JS path
     * accepts text content only.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:'.config('orbita.posts.max_title')],
            'url' => ['nullable', 'url:http,https', 'max:'.config('orbita.posts.max_url_length')],
            'content' => ['nullable', 'string', 'max:'.config('orbita.posts.max_content')],
        ]);

        $url = isset($validated['url']) ? trim((string) $validated['url']) : '';
        $content = (string) ($validated['content'] ?? '');

        if ($url === '' && trim($content) === '') {
            throw ValidationException::withMessages([
                'content' => 'Informe uma URL ou um conteúdo.',
            ]);
        }

        $userId = (int) Auth::id();

        $antispam = $this->postService->checkAntispam($userId, $validated['title'], $url !== '' ? $url : null, $content);
        if (! $antispam['allowed']) {
            throw ValidationException::withMessages(['title' => $antispam['reason'] ?? 'Muitos posts.']);
        }

        try {
            $post = $this->postService->createPost([
                'user_id' => $userId,
                'title' => $validated['title'],
                'url' => $url !== '' ? $url : null,
                'content' => $content,
                'status' => 'published',
            ]);
        } catch (\RuntimeException $e) {
            // Disallowed markdown (raw HTML, headings, code blocks, rules).
            return back()->withInput()->withErrors(['content' => $e->getMessage()]);
        }

        return redirect()->route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);
    }

    /**
     * GET /p/{hashid}/edit — edit form (mounts the post-composer component).
     */
    public function edit(string $hashid): View
    {
        $post = Post::query()
            ->with('media')
            ->where('hashid', $hashid)
            ->whereNull('version_of')
            ->firstOrFail();

        abort_unless($this->canEdit($post), 403);

        return view('posts.edit', ['post' => $post]);
    }

    /**
     * PUT /p/{hashid} — update a post (no-JS fallback for the composer).
     */
    public function update(Request $request, string $hashid): RedirectResponse
    {
        $post = Post::query()->where('hashid', $hashid)->whereNull('version_of')->firstOrFail();

        abort_unless($this->canEdit($post), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:'.config('orbita.posts.max_title')],
            'url' => ['nullable', 'url:http,https', 'max:'.config('orbita.posts.max_url_length')],
            'content' => ['nullable', 'string', 'max:'.config('orbita.posts.max_content')],
        ]);

        // Edit-window enforcement lives in PostService; staff bypass it.
        $this->postService->assertWithinEditWindow($post);

        $url = isset($validated['url']) ? trim((string) $validated['url']) : '';
        $content = (string) ($validated['content'] ?? '');

        // Same guard as store(): both URL and body cannot be blank.
        if ($url === '' && trim($content) === '') {
            throw ValidationException::withMessages([
                'content' => 'Informe uma URL ou um conteúdo.',
            ]);
        }

        try {
            $this->postService->updatePost($post, [
                'title' => $validated['title'],
                'url' => $url !== '' ? $url : null,
                'content' => $content,
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['content' => $e->getMessage()]);
        }

        return redirect()->route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);
    }

    /**
     * DELETE /p/{hashid} — soft-delete a post (owner or moderator/admin).
     */
    public function destroy(string $hashid): RedirectResponse
    {
        $post = Post::query()->where('hashid', $hashid)->whereNull('version_of')->firstOrFail();

        abort_unless($this->canDelete($post), 403);

        $post->delete();

        return redirect()->route('home');
    }

    /**
     * GET /p/{hashid}/revisions — current version + immutable revision snapshots.
     */
    public function revisions(string $hashid): View
    {
        $post = Post::query()
            ->with('user')
            ->where('hashid', $hashid)
            ->whereNull('version_of')
            ->firstOrFail();

        abort_unless($this->canViewRevisions($post), 403);

        $versions = Post::query()
            ->where('version_of', $post->id)
            ->orderBy('id')
            ->get();

        return view('posts.revisions', [
            'post' => $post,
            'versions' => $versions,
        ]);
    }

    // Authorization delegates to PostPolicy; thin wrappers for abort_unless() and view flags.
    private function canEdit(Post $post): bool
    {
        return Auth::user()?->can('update', $post) ?? false;
    }

    private function canDelete(Post $post): bool
    {
        return Auth::user()?->can('delete', $post) ?? false;
    }

    private function canViewRevisions(Post $post): bool
    {
        return Auth::user()?->can('viewRevisions', $post) ?? false;
    }
}
