<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Models\User;
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
            ->whereNull('version_of')
            ->whereIn('status', ['published', 'closed'])
            ->orderByDesc('created_at')
            ->paginate((int) config('orbita.pagination.profile_per_page'), ['*'], self::PAGE)
            ->withPath(\App\Support\Url::paginationPath());
    }
}; ?>

<div x-data="infiniteUrl">
    <ul class="space-y-2">
        @island('profile-posts-rows')
            <li style="height:0;margin:0" aria-hidden="true"
                data-page-marker="{{ $this->posts->currentPage() }}"
                data-page-param="posts"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->posts->previousPageUrl(), 'posts') }}"
                data-next-url="{{ $this->posts->nextPageUrl() }}"></li>

            @forelse ($this->posts as $post)
                <li wire:key="profile-post-{{ $post->id }}" class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                    <a href="{{ route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]) }}" wire:navigate
                       class="font-medium text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400">
                        {{ $post->title }}
                    </a>
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ $post->created_at?->locale('pt_BR')->diffForHumans() }}</span>
                        @if (config('orbita.posts.show_score', true))
                            <span aria-hidden="true">&middot;</span>
                            <span>{{ (int) $post->score }} pontos</span>
                        @endif
                        <span aria-hidden="true">&middot;</span>
                        <span>{{ (int) $post->comment_count }} {{ (int) $post->comment_count === 1 ? 'comentário' : 'comentários' }}</span>
                    </div>
                </li>
            @empty
                @if ($this->posts->currentPage() === 1)
                    <li class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Nenhum post ainda.</li>
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
