<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Services\RankingService;
use App\Support\FeedRowMeta;
use App\Support\HomeFeedTabs;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    #[Locked]
    public string $tab = HomeFeedTabs::DEFAULT;

    /**
     * Whether this tab is the site's main one - the only feed where RankingService sorts by
     * is_pinned, and therefore the only one where the pin next to a title explains anything.
     */
    #[Locked]
    public bool $showsPinned = false;

    public int $perPage = 25;

    public function mount(string $tab = HomeFeedTabs::DEFAULT): void
    {
        $this->tab = HomeFeedTabs::exists($tab) ? $tab : HomeFeedTabs::defaultTab();
        $this->showsPinned = $this->tab === HomeFeedTabs::defaultTab();
        $this->perPage = max(1, (int) config('orbita.posts.per_page', 25));
    }

    protected function rowsIsland(string $pageName = 'page'): string
    {
        return 'feed-rows';
    }

    protected function navIsland(string $pageName = 'page'): string
    {
        return 'feed-nav';
    }

    /**
     * A @php variable from the parent template is invisible inside an island - the island
     * body compiles to its own file and only receives $this plus the public properties.
     * Anything the island needs has to be reachable through $this.
     */
    #[Computed]
    public function isComments(): bool
    {
        return (HomeFeedTabs::get($this->tab)['kind'] ?? 'posts') === 'comments';
    }

    #[Computed]
    public function canAdmin(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    #[Computed]
    public function posts()
    {
        $meta = HomeFeedTabs::get($this->tab);

        if ($this->isComments) {
            return app(RankingService::class)->paginateComments($this->perPage, $this->getPage());
        }

        $method = $this->showsLatestInstead ? HomeFeedTabs::get('all')['method'] : $meta['method'];

        return app(RankingService::class)->paginatePosts($method, $this->perPage, $this->getPage());
    }

    #[Computed]
    public function showsLatestInstead(): bool
    {
        return $this->tab === 'popular'
            && app(RankingService::class)->feedTotal(HomeFeedTabs::get('popular')['method']) === 0;
    }

    #[Computed]
    public function rowMeta(): array
    {
        return FeedRowMeta::for(FeedRowMeta::idsOf($this->posts->items()));
    }
}; ?>

@php $tabs = \App\Support\HomeFeedTabs::all(); $current = $tabs[$tab]; @endphp

<div class="space-y-6" x-data="infiniteUrl">
    <div class="relative -mx-4 md:mx-0" x-data="scrollHint">
        <nav x-ref="scroller"
             class="flex snap-x snap-proximity gap-1.5 overflow-x-auto overscroll-x-contain scroll-px-4 px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:flex-wrap md:gap-y-1.5 md:overflow-x-visible md:scroll-px-0 md:px-0"
             aria-label="Filtros do feed">
            @foreach ($tabs as $key => $meta)
                @php $active = $key === $tab; @endphp
                <a href="{{ route(\App\Support\HomeFeedTabs::routeName($key)) }}" wire:navigate
                   @if ($active) aria-current="page" x-init="$el.scrollIntoView({ block: 'nearest', inline: 'nearest' })" @endif
                   @class([
                       'inline-flex shrink-0 snap-start items-center whitespace-nowrap rounded-full px-3 py-1.5 text-sm font-medium transition',
                       'bg-primary-600 text-white' => $active,
                       'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-gray-800/80 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-gray-100' => ! $active,
                   ])>
                    {{ $meta['label'] }}
                </a>
            @endforeach
        </nav>

        <button type="button" x-show="! atStart" x-cloak tabindex="-1" aria-hidden="true"
                @click="scrollBy(-1)"
                class="absolute inset-y-0 left-0 flex w-12 items-center justify-start bg-gradient-to-r from-gray-50 from-50% to-transparent pl-1 text-gray-500 md:hidden dark:from-gray-950 dark:text-gray-400">
            <x-heroicon-o-chevron-left class="h-4 w-4" aria-hidden="true" />
        </button>
        <button type="button" x-show="! atEnd" x-cloak tabindex="-1" aria-hidden="true"
                @click="scrollBy(1)"
                class="absolute inset-y-0 right-0 flex w-12 items-center justify-end bg-gradient-to-l from-gray-50 from-50% to-transparent pr-1 text-gray-500 md:hidden dark:from-gray-950 dark:text-gray-400">
            <x-heroicon-o-chevron-right class="h-4 w-4" aria-hidden="true" />
        </button>
    </div>

    <p class="mt-3 mb-0 text-sm text-gray-500 dark:text-gray-400">
        @if ($this->showsLatestInstead)
            Nada ganhou tração nos últimos 7 dias. Enquanto isso, os posts mais recentes.
        @else
            {{ $current['description'] }}
        @endif
    </p>

    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
        @island('feed-rows')
            <li style="height:0;border-width:0" aria-hidden="true"
                data-page-marker="{{ $this->posts->currentPage() }}"
                data-page-param="page"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->posts->previousPageUrl()) }}"
                data-next-url="{{ $this->posts->nextPageUrl() }}"></li>

            @forelse ($this->posts as $row)
                @if ($this->isComments)
                    @include('partials.comment-feed-item', ['comment' => $row])
                @else
                    @include('partials.post-feed-item', [
                        'post' => $row,
                        'meta' => $this->rowMeta,
                        'canAdmin' => $this->canAdmin,
                        'showsPinned' => $showsPinned,
                    ])
                @endif
            @empty
                @if ($this->posts->currentPage() === 1)
                    <li class="rounded-lg border border-gray-200 bg-white p-6 text-center text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                        @if ($this->isComments)
                            Nenhum comentário encontrado.
                        @else
                            Nenhum post encontrado.
                            <a href="{{ route('posts.create') }}" wire:navigate class="font-medium text-primary-600 hover:underline dark:text-primary-400">Seja o primeiro a postar!</a>
                        @endif
                    </li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('feed-nav')
        <x-infinite-pagination :paginator="$this->posts" page-name="page" island="feed-rows"
                               :noun="$this->isComments ? 'comentários' : 'posts'" />
    @endisland
</div>
