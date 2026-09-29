<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Models\Post;
use App\Support\FeedRowMeta;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    #[Locked]
    public int $tagId = 0;

    public function mount(int $tagId): void
    {
        $this->tagId = $tagId;
    }

    protected function rowsIsland(string $pageName = 'page'): string
    {
        return 'tag-rows';
    }

    protected function navIsland(string $pageName = 'page'): string
    {
        return 'tag-nav';
    }

    #[Computed]
    public function posts()
    {
        return Post::query()
            ->with('user')
            ->whereIn('status', ['published', 'closed'])
            ->whereNull('version_of')
            ->whereHas('terms', fn ($query) => $query->where('terms.id', $this->tagId))
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(max(1, (int) config('orbita.posts.per_page', 25)))
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
        @island('tag-rows')
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
                    'currentTagId' => $tagId,
                ])
            @empty
                @if ($this->posts->currentPage() === 1)
                    <li class="py-8 text-center text-gray-500 dark:text-gray-400">Ainda não há posts com esta tag.</li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('tag-nav')
        <x-infinite-pagination :paginator="$this->posts" page-name="page" island="tag-rows" noun="posts" />
    @endisland
</div>
