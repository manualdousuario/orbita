<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Services\SearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'type', except: 'all')]
    public string $type = 'all';

    public int $perPage = 10;

    protected function rowsIsland(string $pageName = 'page'): string
    {
        return 'search-rows';
    }

    protected function navIsland(string $pageName = 'page'): string
    {
        return 'search-nav';
    }

    public function updatedQuery(): void
    {
        $this->resetSearch();
    }

    public function updatedType(): void
    {
        $this->resetSearch();
    }

    /**
     * A new query invalidates everything already appended, so the rows island is morphed
     * back to page one rather than added to. The summary line lives in its own island
     * because resetInfinite() calls skipRender() - without an explicit fragment it would
     * keep showing the count for the previous query.
     */
    private function resetSearch(): void
    {
        $this->resetInfinite();

        $this->renderIsland(name: 'search-summary', mode: 'morph');
    }

    #[Computed]
    public function hasQuery(): bool
    {
        return trim($this->query) !== '';
    }

    #[Computed]
    public function results()
    {
        if (! $this->hasQuery) {
            return new LengthAwarePaginator([], 0, $this->perPage, 1, [
                'path' => \App\Support\Url::paginationPath(),
                'pageName' => 'page',
            ]);
        }

        return app(SearchService::class)
            ->searchUnifiedPaginated(trim($this->query), ['type' => $this->type], $this->perPage, $this->getPage())
            ->appends(array_filter([
                'q' => trim($this->query),
                'type' => $this->type === 'all' ? null : $this->type,
            ]));
    }

    #[Computed]
    public function total(): int
    {
        return $this->results->total();
    }
}; ?>

<div class="space-y-6" x-data="infiniteUrl">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Buscar</h1>

    <form action="{{ route('search') }}" method="GET" wire:submit.prevent="" class="space-y-3">
        <div class="flex gap-2">
            <input type="search" name="q" wire:model.live.debounce.300ms="query" placeholder="O que você está procurando?" aria-label="Buscar"
                   class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
            <button type="submit" class="shrink-0 rounded-md bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-700 data-loading:opacity-50">
                Buscar
            </button>
        </div>
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <span class="text-gray-500 dark:text-gray-400">Tipo:</span>
            @foreach (['all' => 'Todos', 'posts' => 'Posts', 'comments' => 'Comentários'] as $value => $label)
                <label class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
                    <input type="radio" name="type" wire:model.live="type" value="{{ $value }}"
                           @checked($type === $value)
                           class="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </form>

    @island('search-summary')
        @if ($this->hasQuery && $this->total > 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <strong>{{ $this->total }}</strong> {{ $this->total === 1 ? 'resultado' : 'resultados' }} para <strong>"{{ trim($this->query) }}"</strong>.
            </p>
        @endif
    @endisland

    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
        @island('search-rows')
            <li style="height:0;border-width:0" aria-hidden="true"
                data-page-marker="{{ $this->results->currentPage() }}"
                data-page-param="page"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->results->previousPageUrl()) }}"
                data-next-url="{{ $this->results->nextPageUrl() }}"></li>

            @if (! $this->hasQuery)
                <li class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                    <x-heroicon-o-magnifying-glass class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" aria-hidden="true" />
                    <p class="mt-3">Digite algo para buscar posts e comentários.</p>
                </li>
            @elseif ($this->total === 0)
                <li class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    Nenhum resultado encontrado para <strong>"{{ trim($this->query) }}"</strong>.
                </li>
            @else
                @foreach ($this->results as $item)
                    @php
                        $url = $item->type === 'post'
                            ? route('posts.show', ['hashid' => $item->hashid, 'slug' => $item->slug])
                            : route('posts.show', ['hashid' => $item->post_hashid, 'slug' => $item->post_slug]) . '#comment-' . $item->hashid;
                        $locked = $item->type === 'post' && ! $item->allow_comments;
                    @endphp
                    @php
                        $isComment = $item->type === 'comment';
                        $author = $item->anonymized_at ? \App\Actions\AnonymizeUser::DISPLAY_NAME : ($item->display_name ?: $item->username);
                    @endphp
                    <li wire:key="result-{{ $item->type }}-{{ $item->hashid }}" class="py-4">
                        <h2 class="text-base font-semibold leading-snug text-balance sm:text-lg">
                            <x-post-locked-badge :locked="$locked" />
                            @if ($isComment)
                                <span class="sr-only">Comentário em</span>
                            @endif
                            <a href="{{ $url }}" wire:navigate
                               class="break-words text-gray-900 visited:text-visited hover:text-primary-600 dark:text-gray-100 dark:visited:text-visited dark:hover:text-primary-400">
                                {{ $item->title }}
                            </a>
                        </h2>

                        @if (filled($item->content))
                            <p @class([
                                'mt-1 max-w-[64ch] text-gray-700 dark:text-gray-300 [&_mark]:rounded-sm [&_mark]:bg-amber-200/70 [&_mark]:px-0.5 [&_mark]:text-gray-900 dark:[&_mark]:bg-amber-400/25 dark:[&_mark]:text-amber-100',
                                'font-serif text-[0.95rem] leading-relaxed' => $isComment,
                                'text-sm' => ! $isComment,
                            ])>{{ \App\Support\SearchSnippet::make((string) $item->content, (string) $this->query) }}</p>
                        @endif

                        <p class="mt-1 flex flex-wrap items-center gap-x-1.5 font-mono text-sm text-gray-500 dark:text-gray-400">
                            @if ($isComment)
                                <x-heroicon-o-chat-bubble-left class="h-3.5 w-3.5" aria-hidden="true" />
                                <span>comentário de</span>
                            @endif
                            @if ($item->username && ! $item->anonymized_at)
                                <a href="{{ route('users.profile', ['username' => $item->username]) }}" wire:navigate
                                   class="hover:text-primary-600 hover:underline dark:hover:text-primary-400">{{ $author }}</a>
                            @else
                                <span>{{ $author }}</span>
                            @endif
                            @if ($item->created_at)
                                <span aria-hidden="true">&middot;</span>
                                <x-date-time :value="\Illuminate\Support\Carbon::parse($item->created_at)" />
                            @endif
                        </p>
                    </li>
                @endforeach
            @endif
        @endisland
    </ul>

    @island('search-nav')
        @if ($this->hasQuery)
            <x-infinite-pagination :paginator="$this->results" page-name="page"
                                   island="search-rows" noun="resultados" />
        @endif
    @endisland
</div>
