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
                <strong>{{ $this->total }}</strong> resultado(s) para <strong>"{{ trim($this->query) }}"</strong>.
            </p>
        @endif
    @endisland

    <ul class="space-y-2">
        @island('search-rows')
            <li style="height:0;margin:0" aria-hidden="true"
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
                    <li wire:key="result-{{ $item->type }}-{{ $item->hashid }}"
                        class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-2">
                            <span class="min-w-0">
                                <x-post-locked-badge :locked="$locked" />
                                <a href="{{ $url }}" wire:navigate
                                   class="font-medium text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400">
                                    {{ $item->title }}
                                </a>
                            </span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-sm font-medium {{ $item->type === 'post' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                {{ $item->type === 'post' ? 'Post' : 'Comentário' }}
                            </span>
                        </div>
                        @if (! empty($item->content))
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit(strip_tags((string) $item->content), 160) }}</p>
                        @endif
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">por {{ $item->display_name ?: $item->username }}</p>
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
