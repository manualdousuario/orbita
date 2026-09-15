<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    public const PAGE = 'comments';

    #[Locked]
    public int $userId = 0;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    protected function rowsIsland(string $pageName = self::PAGE): string
    {
        return 'profile-comments-rows';
    }

    protected function navIsland(string $pageName = self::PAGE): string
    {
        return 'profile-comments-nav';
    }

    #[Computed]
    public function comments()
    {
        return User::query()->findOrFail($this->userId)
            ->comments()
            ->whereNull('version_of')
            ->where('status', 'visible')
            ->with('post:id,hashid,slug,title')
            ->orderByDesc('created_at')
            ->paginate((int) config('orbita.pagination.profile_per_page'), ['*'], self::PAGE)
            ->withPath(\App\Support\Url::paginationPath());
    }
}; ?>

<div x-data="infiniteUrl">
    <ul class="space-y-2">
        @island('profile-comments-rows')
            <li style="height:0;margin:0" aria-hidden="true"
                data-page-marker="{{ $this->comments->currentPage() }}"
                data-page-param="comments"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->comments->previousPageUrl(), 'comments') }}"
                data-next-url="{{ $this->comments->nextPageUrl() }}"></li>

            @forelse ($this->comments as $comment)
                <li wire:key="profile-comment-{{ $comment->id }}" class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                    @if ($comment->post)
                        <a href="{{ route('posts.show', ['hashid' => $comment->post->hashid, 'slug' => $comment->post->slug]) }}#comment-{{ $comment->hashid }}" wire:navigate
                           class="text-sm text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400">
                            {{ \Illuminate\Support\Str::limit((string) $comment->content, 160) }}
                        </a>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            em "{{ \Illuminate\Support\Str::limit((string) $comment->post->title, 60) }}"
                            &middot; <x-date-time :value="$comment->created_at" />
                        </p>
                    @endif
                </li>
            @empty
                @if ($this->comments->currentPage() === 1)
                    <li class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Nenhum comentário ainda.</li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('profile-comments-nav')
        <div class="mt-3">
            <x-infinite-pagination :paginator="$this->comments" page-name="comments"
                                   island="profile-comments-rows" noun="comentários" />
        </div>
    @endisland
</div>
