<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Models\User;
use App\Support\FeedRowMeta;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    public const PAGE = 'posts';

    #[Locked]
    public int $userId = 0;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    protected function rowsIsland(string $pageName = self::PAGE): string
    {
        return 'profile-posts-rows';
    }

    protected function navIsland(string $pageName = self::PAGE): string
    {
        return 'profile-posts-nav';
    }

    #[Computed]
    public function posts()
    {
        return User::query()->findOrFail($this->userId)
            ->posts()
            ->with('user')
            ->whereNull('version_of')
            ->whereIn('status', ['published', 'closed'])
            ->orderByDesc('created_at')
            ->paginate((int) config('orbita.pagination.profile_per_page'), ['*'], self::PAGE)
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
        @island('profile-posts-rows')
            <li style="height:0;border-width:0" aria-hidden="true"
                data-page-marker="{{ $this->posts->currentPage() }}"
                data-page-param="posts"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->posts->previousPageUrl(), 'posts') }}"
                data-next-url="{{ $this->posts->nextPageUrl() }}"></li>

            @forelse ($this->posts as $post)
                @include('partials.post-feed-item', [
                    'post' => $post,
                    'meta' => $this->rowMeta,
                    'canAdmin' => $this->canAdmin,
                    'hideAuthor' => true,
                ])
            @empty
                @if ($this->posts->currentPage() === 1)
                    <li class="py-6 text-sm text-gray-500 dark:text-gray-400">Nenhum post ainda.</li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('profile-posts-nav')
        <div class="mt-3">
            <x-infinite-pagination :paginator="$this->posts" page-name="posts"
                                   island="profile-posts-rows" noun="posts" />
        </div>
    @endisland
</div>
