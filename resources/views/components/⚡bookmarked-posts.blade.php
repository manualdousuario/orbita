<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Services\BookmarkService;
use App\Support\FeedRowMeta;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    protected function rowsIsland(string $pageName = 'page'): string
    {
        return 'bookmarked-rows';
    }

    protected function navIsland(string $pageName = 'page'): string
    {
        return 'bookmarked-nav';
    }

    #[Computed]
    public function posts()
    {
        return app(BookmarkService::class)
            ->listFor((int) Auth::id(), (int) config('orbita.pagination.bookmarks_per_page'))
            ->withPath(\App\Support\Url::paginationPath());
    }

    #[Computed]
    public function rowMeta(): array
    {
        return FeedRowMeta::for(FeedRowMeta::idsOf($this->posts->items()));
    }

    #[Computed]
    public function canAdmin(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }
}; ?>

<div x-data="infiniteUrl">
    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
        @island('bookmarked-rows')
            <li style="height:0;border-width:0" aria-hidden="true"
                data-page-marker="{{ $this->posts->currentPage() }}"
                data-page-param="page"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->posts->previousPageUrl()) }}"
                data-next-url="{{ $this->posts->nextPageUrl() }}"></li>

            @forelse ($this->posts as $post)
                @include('partials.post-feed-item', [
                    'post' => $post,
                    'meta' => $this->rowMeta,
                    'canAdmin' => $this->canAdmin,
                ])
            @empty
                @if ($this->posts->currentPage() === 1)
                    <li class="rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700">
                        <x-heroicon-o-bookmark class="mx-auto h-8 w-8 text-gray-500" aria-hidden="true" />
                        <p class="mt-3 text-gray-600 dark:text-gray-300">Você ainda não acompanha nenhum post.</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Toque em <span class="font-medium">Acompanhar</span> em qualquer post para ver aqui os novos comentários.
                        </p>
                    </li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('bookmarked-nav')
        <x-infinite-pagination :paginator="$this->posts" page-name="page" island="bookmarked-rows" noun="posts" />
    @endisland
</div>
