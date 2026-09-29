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
    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
        @island('profile-comments-rows')
            <li style="height:0;border-width:0" aria-hidden="true"
                data-page-marker="{{ $this->comments->currentPage() }}"
                data-page-param="comments"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->comments->previousPageUrl(), 'comments') }}"
                data-next-url="{{ $this->comments->nextPageUrl() }}"></li>

            @forelse ($this->comments as $comment)
                @if ($comment->post)
                    @php
                        $postUrl = route('posts.show', ['hashid' => $comment->post->hashid, 'slug' => $comment->post->slug]);
                        $excerpt = html_entity_decode(\App\Support\Markdown::excerpt((string) $comment->content, 280), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    @endphp
                    <li wire:key="profile-comment-{{ $comment->id }}" class="py-4">
                        <p class="flex flex-wrap items-baseline gap-x-1.5 text-sm text-gray-500 dark:text-gray-400">
                            <span>em</span>
                            <a href="{{ $postUrl }}" wire:navigate
                               class="font-semibold text-gray-900 visited:text-visited hover:text-primary-600 dark:text-gray-100 dark:visited:text-visited dark:hover:text-primary-400">{{ $comment->post->title }}</a>
                            <span aria-hidden="true">&middot;</span>
                            <a href="{{ $postUrl }}#comment-{{ $comment->hashid }}" wire:navigate
                               class="font-mono hover:text-primary-600 dark:hover:text-primary-400"><x-date-time :value="$comment->created_at" /></a>
                        </p>
                        <p class="mt-1 max-w-[64ch] font-serif text-[0.95rem] leading-relaxed text-gray-800 dark:text-gray-200">{{ $excerpt }}</p>
                    </li>
                @endif
            @empty
                @if ($this->comments->currentPage() === 1)
                    <li class="py-6 text-sm text-gray-500 dark:text-gray-400">Nenhum comentário ainda.</li>
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
