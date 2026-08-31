<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Models\Comment;
use App\Services\CommentService;
use App\Services\ReactionService;
use App\Services\ReportService;
use App\Support\Markdown;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    public const PAGE = \App\Support\Url::COMMENT_PAGE;

    #[Locked]
    public int $postId = 0;

    #[Locked]
    public bool $allowComments = true;

    #[Locked]
    public string $sort = 'newest';

    /** Batched per render, not Livewire state. @var array<int, array<string, mixed>> */
    private array $summaries = [];

    /** @var array<int, string> */
    private array $myReactions = [];

    /** Comments the current user already reported (batched, flipped for isset()). @var array<int, int> */
    private array $reportedIds = [];

    /** Who wrote the post - the "OP" tag on their comments. One query, not one per node. */
    private ?int $postAuthorId = null;

    /**
     * Per-request memo of the page build (paginator + nested tree). Not Livewire state:
     * $this->tree and $this->roots are both read more than once by the blade, and no write
     * to `comments` happens inside this component, so it cannot go stale mid-request.
     *
     * @var array{paginator: LengthAwarePaginator, tree: array<int, array<string, mixed>>}|null
     */
    private ?array $pageMemo = null;

    private ?int $totalMemo = null;

    /**
     * Set only while handling comment-created: restricts the build to these root ids so the
     * island can render just the new comment and have it prepended.
     *
     * @var list<int>
     */
    private array $forcedRootIds = [];

    private bool $renderAllLoadedPages = false;

    public function mount(int $postId, bool $allowComments = true): void
    {
        $this->postId = $postId;
        $this->allowComments = $allowComments;

        $requested = (string) request()->query('sort', '');

        $this->sort = in_array($requested, ['newest', 'oldest', 'most_reactions'], true)
            ? $requested
            : (string) (auth()->user()?->default_comment_sort ?? config('orbita.comments.default_sort', 'newest'));
    }

    protected function rowsIsland(string $pageName = self::PAGE): string
    {
        return 'comment-roots';
    }

    protected function navIsland(string $pageName = self::PAGE): string
    {
        return 'comment-nav';
    }

    #[On('comment-created')]
    public function onCommentCreated(int $postId, ?int $parentId = null, ?int $commentId = null): void
    {
        if ($postId !== $this->postId) {
            $this->skipRender();

            return;
        }

        $this->pageMemo = null;
        $this->totalMemo = null;

        if ($parentId === null && $this->sort === 'newest') {
            $newId = $this->newestRootId($commentId);

            if ($newId !== null) {
                $this->forcedRootIds = [$newId];

                $this->renderIsland(name: 'comment-roots', mode: 'prepend');
                $this->renderIsland(name: 'comment-toolbar', mode: 'morph');

                $this->skipRender();

                return;
            }
        }

        $this->renderAllLoadedPages = true;

        $this->renderIsland(name: 'comment-roots', mode: 'morph');
        $this->renderIsland(name: 'comment-toolbar', mode: 'morph');

        $this->skipRender();
    }

    private function newestRootId(?int $hint): ?int
    {
        $query = fn () => Comment::query()
            ->where('post_id', $this->postId)
            ->where('status', 'visible')
            ->whereNull('version_of')
            ->whereNull('parent_id');

        $id = $hint === null ? null : $query()->whereKey($hint)->value('id');

        $id ??= $query()->orderByDesc('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    public function getPageMarkersProperty(): array
    {
        if ($this->forcedRootIds !== []) {
            return [];
        }

        $paginator = $this->roots;
        $perPage = max(1, (int) $paginator->perPage());
        $current = (int) $paginator->currentPage();
        $first = $this->renderAllLoadedPages ? 1 : $current;

        $markers = [];

        for ($page = $first; $page <= $current; $page++) {
            $markers[($page - $first) * $perPage] = [
                'page' => $page,
                'prev' => \App\Support\Url::canonicalPage(
                    $page > 1 ? $paginator->url($page - 1) : null,
                    self::PAGE,
                ),
                'next' => $page < $paginator->lastPage() ? $paginator->url($page + 1) : null,
            ];
        }

        return $markers;
    }

    /** @return array<int, array<string, mixed>> nested comment nodes */
    public function getTreeProperty(): array
    {
        return $this->buildPage()['tree'];
    }

    /** The root-comment paginator, which is what ?comentarios=N addresses. */
    public function getRootsProperty(): LengthAwarePaginator
    {
        return $this->buildPage()['paginator'];
    }

    private function buildPage(): array
    {
        if ($this->pageMemo !== null) {
            return $this->pageMemo;
        }

        $all = Comment::query()
            ->with(['user', 'media'])
            ->where('post_id', $this->postId)
            ->where('status', 'visible')
            ->whereNull('version_of')
            ->get();

        $visibleIds = array_flip($all->pluck('id')->map(fn ($id) => (int) $id)->all());

        [$sortColumn, $sortDirection] = CommentService::rootOrdering($this->sort);

        $allRoots = $all
            ->filter(fn (Comment $c) => $c->parent_id === null || ! isset($visibleIds[(int) $c->parent_id]))
            ->sort(function (Comment $a, Comment $b) use ($sortColumn, $sortDirection) {
                $cmp = $a->{$sortColumn} <=> $b->{$sortColumn};
                $cmp = $sortDirection === 'asc' ? $cmp : -$cmp;

                return $cmp ?: ($sortDirection === 'asc' ? $a->id <=> $b->id : $b->id <=> $a->id);
            })
            ->values();

        $perPage = (int) config('orbita.comments.per_page', 20);
        $page = max(1, (int) $this->getPage(self::PAGE));

        $offset = $this->renderAllLoadedPages ? 0 : ($page - 1) * $perPage;
        $length = $this->renderAllLoadedPages ? $perPage * $page : $perPage;

        $paginator = new LengthAwarePaginator(
            $allRoots->slice($offset, $length)->values(),
            $allRoots->count(),
            $perPage,
            $page,
            [
                'path' => \App\Support\Url::paginationPath(),
                'pageName' => self::PAGE,
            ],
        );

        if ($this->sort !== (string) config('orbita.comments.default_sort', 'newest')) {
            $paginator->appends(['sort' => $this->sort]);
        }

        $roots = $this->forcedRootIds === []
            ? $paginator->getCollection()
            : $allRoots->whereIn('id', $this->forcedRootIds)->values();

        $childrenOf = $all->groupBy(fn (Comment $c) => (int) $c->parent_id);

        $comments = $roots;
        $frontier = $roots->pluck('id')->map(fn ($id) => (int) $id)->all();
        $depth = 0;
        $maxDepth = (int) config('orbita.comments.max_nesting_level', 5);

        while ($frontier !== [] && $depth++ < $maxDepth) {
            $children = collect($frontier)->flatMap(fn ($id) => $childrenOf->get($id, collect()));

            if ($children->isEmpty()) {
                break;
            }

            $comments = $comments->concat($children);
            $frontier = $children->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $ids = $comments->pluck('id')->map(fn ($id) => (int) $id)->all();
        $rootIds = array_flip($roots->pluck('id')->map(fn ($id) => (int) $id)->all());

        $reactions = app(ReactionService::class);
        $this->summaries = $reactions->summariesFor('comment', $ids);
        $this->myReactions = $reactions->userReactionsFor(auth()->id(), 'comment', $ids);
        $this->reportedIds = array_flip(app(ReportService::class)->reportedIdsFor(auth()->id(), 'comment', $ids));
        $this->postAuthorId = (int) DB::table('posts')->where('id', $this->postId)->value('user_id') ?: null;

        $byParent = [];
        foreach ($comments as $comment) {
            $isRoot = isset($rootIds[(int) $comment->id]);
            $byParent[$isRoot ? 0 : (int) $comment->parent_id][] = $comment;
        }

        $this->totalMemo = $all->count();

        return $this->pageMemo = [
            'paginator' => $paginator,
            'tree' => $this->build($byParent, 0),
        ];
    }

    public function getTotalProperty(): int
    {
        if ($this->totalMemo === null) {
            $this->buildPage();
        }

        return (int) $this->totalMemo;
    }

    /**
     * @param  array<int, array<int, Comment>>  $byParent
     * @return array<int, array<string, mixed>>
     */
    private function build(array $byParent, int $parentId): array
    {
        $children = $byParent[$parentId] ?? [];

        $children = collect($children)->sort(function (Comment $a, Comment $b) {
            return match ($this->sort) {
                'oldest' => $a->created_at <=> $b->created_at,
                'most_reactions' => ((int) $b->score <=> (int) $a->score)
                    ?: ($b->created_at <=> $a->created_at),
                default => $b->created_at <=> $a->created_at,
            };
        })->values();

        $user = auth()->user();
        $maxNesting = (int) config('orbita.comments.max_nesting_level', 5);
        $comments = app(CommentService::class);

        return $children->map(function (Comment $c) use ($byParent, $user, $maxNesting, $comments) {
            $isOwner = $user !== null && (int) $user->id === (int) $c->user_id;
            $isStaff = $user?->isStaff() ?? false;
            $id = (int) $c->id;

            $replies = $this->build($byParent, $id);

            $descendants = count($replies) + array_sum(array_column($replies, 'descendant_count'));

            return [
                'id' => $id,
                'hashid' => $c->hashid,
                'content_html' => Markdown::toHtml((string) $c->content),
                'author' => $c->user?->authorName() ?? 'Anônimo',
                'username' => $c->user?->isLinkable() ? $c->user->username : null,
                'user' => $c->user,
                'created_at' => $c->created_at,
                'edited_at' => $c->edited_at,
                'nesting_level' => (int) $c->nesting_level,
                'is_op' => $this->postAuthorId !== null && (int) $c->user_id === $this->postAuthorId,
                'media' => $c->media,
                'can_reply' => $user !== null && (int) $c->nesting_level < $maxNesting,
                'can_edit' => $comments->canEdit($c, $user),
                'can_delete' => $isOwner || $isStaff,
                'can_admin' => $isStaff,
                'reaction_summary' => ($this->summaries[$id] ?? ['reactions' => [], 'score' => 0, 'count' => 0])
                    + ['user_reaction' => $this->myReactions[$id] ?? null],
                'reported' => isset($this->reportedIds[$id]),
                'replies' => $replies,
                'descendant_count' => $descendants,
            ];
        })->all();
    }
}; ?>

<div class="space-y-4" x-data="infiniteUrl">
    @island('comment-toolbar')
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Comentários ({{ $this->total }})
            </h2>

            @if ($this->total > 0)
                @php
                    $default = (string) config('orbita.comments.default_sort', 'newest');
                    $sortUrl = function (string $key) use ($default) {
                        $query = request()->query();
                        unset($query['comentarios']);

                        if ($key === $default) {
                            unset($query['sort']);
                        } else {
                            $query['sort'] = $key;
                        }

                        return url()->current().($query === [] ? '' : '?'.http_build_query($query)).'#comentarios';
                    };
                @endphp
                <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-sm dark:border-gray-700">
                    @foreach (['newest' => 'Novos', 'oldest' => 'Antigos', 'most_reactions' => 'Populares'] as $key => $label)
                        @php $isCurrent = $sort === $key; @endphp
                        <a href="{{ $sortUrl($key) }}"
                           wire:navigate rel="nofollow"
                           @if ($isCurrent) aria-current="true" @endif
                           @class([
                               'px-3 py-3 font-medium transition',
                               'bg-primary-600 text-white' => $isCurrent,
                               'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => ! $isCurrent,
                           ])>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endisland

    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
        @island('comment-roots')
            @php $markers = $this->pageMarkers; @endphp

            @forelse ($this->tree as $node)
                @isset ($markers[$loop->index])
                    <li style="height:0;border-width:0" aria-hidden="true"
                        data-page-marker="{{ $markers[$loop->index]['page'] }}"
                        data-page-param="comentarios"
                        data-prev-url="{{ $markers[$loop->index]['prev'] }}"
                        data-next-url="{{ $markers[$loop->index]['next'] }}"></li>
                @endisset

                @include('partials.comment-node', [
                    'node' => $node,
                    'postId' => $postId,
                    'allowComments' => $allowComments,
                    'isRoot' => true,
                    'depth' => 0,
                ])
            @empty
                @if ($this->roots->currentPage() === 1)
                    <li class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nenhum comentário ainda. Seja o primeiro a comentar.
                    </li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('comment-nav')
        <x-infinite-pagination :paginator="$this->roots" page-name="comentarios"
                               island="comment-roots" noun="comentários" />
    @endisland
</div>
